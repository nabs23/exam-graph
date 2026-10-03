<?php

namespace App;

use App\Exceptions\CurriculumExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\TimeoutExceededException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

enum CurriculumExtractionError: string
{
    case ProviderTimeout = 'provider_timeout';
    case ProviderConnectionTimeout = 'provider_connection_timeout';
    case JobTimeout = 'job_timeout';
    case ProviderConnection = 'provider_connection_failed';
    case ProviderRateLimited = 'provider_rate_limited';
    case ProviderCredits = 'provider_insufficient_credits';
    case ProviderAuthentication = 'provider_authentication_failed';
    case ProviderModelUnavailable = 'provider_model_unavailable';
    case ProviderRequestRejected = 'provider_request_rejected';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderOutputLimit = 'provider_output_limit';
    case ProviderCancelled = 'provider_cancelled';
    case ProviderRefused = 'provider_refused';
    case ProviderFailed = 'provider_failed';
    case InvalidProposal = 'invalid_proposal';
    case NoCurriculumFound = 'no_curriculum_found';
    case SourceChanged = 'source_changed';
    case ExtractionFailed = 'extraction_failed';

    public function message(): string
    {
        return match ($this) {
            self::ProviderTimeout => 'The AI provider did not finish within the extraction wait time. Try a faster model or ask an administrator to increase the wait time.',
            self::ProviderConnectionTimeout => 'The connection to the AI provider timed out. Check the connection and try again.',
            self::JobTimeout => 'The extraction worker stopped before processing finished. Ask an administrator to check the worker configuration, then try again.',
            self::ProviderConnection => 'The application could not connect to the AI provider. Check the connection and try again.',
            self::ProviderRateLimited => 'The AI provider rate limit was reached. Wait a little before trying again.',
            self::ProviderCredits => 'The AI provider has insufficient credits or has exceeded its quota. Ask an administrator to check the provider billing.',
            self::ProviderAuthentication => 'The AI provider rejected the configured credentials. Ask an administrator to check the API key and permissions.',
            self::ProviderModelUnavailable => 'The selected model is unavailable to the configured API account. Choose another model or check model access.',
            self::ProviderRequestRejected => 'The AI provider rejected the extraction request. Ask an administrator to check model compatibility and the server logs.',
            self::ProviderUnavailable => 'The AI provider is temporarily unavailable. Please try again later.',
            self::ProviderOutputLimit => 'The AI response exceeded the model output limit before the curriculum was complete. Try another model or fewer source pages.',
            self::ProviderCancelled => 'The AI provider cancelled this extraction. Start a new extraction to try again.',
            self::ProviderRefused => 'The AI provider declined to process the source content. Check the uploaded PDFs or try another model.',
            self::ProviderFailed => 'The AI provider could not complete this extraction. Try another model; an administrator can check the server logs for the exact cause.',
            self::InvalidProposal => 'The AI response failed curriculum validation, such as source citations or topic hierarchy. Try the extraction again or choose another model.',
            self::NoCurriculumFound => 'No official curriculum structure was found in the source PDFs. Check that the uploaded files contain subjects and syllabus topics.',
            self::SourceChanged => 'The source files changed during extraction. Start a new extraction using the current files.',
            self::ExtractionFailed => 'The extraction could not be processed. Ask an administrator to check the server logs, then try again.',
        };
    }

    public static function fromException(?Throwable $exception): self
    {
        while ($exception !== null) {
            if ($exception instanceof CurriculumExtractionException) {
                return $exception->failure;
            }
            if ($exception instanceof TimeoutExceededException) {
                return self::JobTimeout;
            }
            if ($exception instanceof RateLimitedException) {
                $cause = $exception->getPrevious();

                return $cause instanceof RequestException
                    ? self::fromProviderError(429, $cause->response->json('error.code'))
                    : self::ProviderRateLimited;
            }
            if ($exception instanceof InsufficientCreditsException) {
                return self::ProviderCredits;
            }
            if ($exception instanceof ConnectionException) {
                return str_contains($exception->getMessage(), 'cURL error 28')
                    ? self::ProviderConnectionTimeout : self::ProviderConnection;
            }
            if ($exception instanceof RequestException) {
                return self::fromProviderError($exception->response->status(), $exception->response->json('error.code'));
            }
            $exception = $exception->getPrevious();
        }

        return self::ProviderFailed;
    }

    public static function fromProviderError(int $status, mixed $code): self
    {
        return match (true) {
            $code === 'insufficient_quota' => self::ProviderCredits,
            $code === 'request_timeout' => self::ProviderTimeout,
            $code === 'rate_limit_exceeded' => self::ProviderRateLimited,
            $code === 'invalid_api_key' => self::ProviderAuthentication,
            in_array($code, ['server_error', 'overloaded'], true) => self::ProviderUnavailable,
            in_array($code, ['model_not_found', 'invalid_model'], true), $status === 404 => self::ProviderModelUnavailable,
            in_array($status, [401, 403], true) => self::ProviderAuthentication,
            $status === 429 => self::ProviderRateLimited,
            $status === 400 => self::ProviderRequestRejected,
            $status >= 500 => self::ProviderUnavailable,
            default => self::ProviderFailed,
        };
    }
}
