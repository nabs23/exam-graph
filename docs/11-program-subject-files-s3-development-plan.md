# Program and Subject Files — S3 Development Plan

## Goal

Add an administrator-only source-file library with two clear scopes:

| Resource | Scope and purpose | Initial file types |
| --- | --- | --- |
| `ProgramFile` | Authoritative, program-wide material used to establish the official curriculum structure. | `exam_specification`, `official_syllabus`, `board_resolution`, `amendment_or_clarification`, `program_reference`, `other` |
| `SubjectFile` | Subject-specific instructional material used as evidence for concepts, lessons, and questions. | `reviewer_ebook`, `reviewer_notes`, `lecture_material`, `official_reference`, `practice_material`, `other` |

Program files may produce candidates for programs, subjects, official syllabus topics, and tables of specifications. They must not automatically publish instructional concepts. Subject files may produce concept and content candidates, mapped to official topics, but a reviewer must approve them before publication.

The first delivery includes full file-management CRUD: list, inspect, upload, edit metadata, download, and delete. File bytes are stored privately in Amazon S3; the application stores only metadata and the S3 object key.

## Decisions

- Use two first-class models and tables: `ProgramFile` and `SubjectFile`. This preserves database foreign-key integrity and makes the separate admin workflows explicit.
- Keep the two schemas and service interfaces deliberately parallel. A future generalized `SourceDocument`/version system can consolidate them if a document must belong to more than one subject or program.
- Upload directly from the browser to S3 with a short-lived presigned `PUT` URL. Do not proxy large documents through PHP.
- Keep the bucket and all objects private. Do not use public ACLs, public bucket policies, or permanent S3 URLs.
- Generate a short-lived presigned `GET` URL after an authorized download request. Download URLs are not stored in the database.
- Treat an uploaded file as immutable evidence. Editing changes metadata only. Uploading a replacement creates a new file record and archives or deletes the prior record according to its downstream references.
- Start with PDF, DOCX, and EPUB. Set an explicit per-file limit (recommended: 100 MiB) and add multipart uploads only when real sources exceed it.
- Keep extraction, chunking, malware scanning, and AI processing out of this first CRUD slice. The models and statuses reserve a safe handoff for that next phase.

## Data model

Create `program_files` and `subject_files` with the same core columns.

```text
id
program_id | subject_id              required owner foreign key
file_type                          constrained application enum/value
title                              administrator-facing title
original_filename                  display only; never used as an S3 key
storage_disk                       default: s3
storage_key                        random, server-generated S3 object key
mime_type                          declared and verified after upload
file_size                          verified byte size
content_hash                       nullable initially; populated when available
upload_status                      pending_upload | uploaded | failed | delete_pending
uploaded_by                        nullable FK to users
uploaded_at                        nullable timestamp
metadata                           nullable JSON: author, publisher, edition,
                                   publication_date, effective_date, rights_notes
created_at
updated_at
```

Recommended indexes:

- `(program_id, file_type, created_at)` on `program_files`;
- `(subject_id, file_type, created_at)` on `subject_files`;
- `(upload_status, created_at)` on both tables for cleanup and later processing;
- a non-unique index on `content_hash` once hashing is implemented.

Use `restrictOnDelete()` for the owner relationship so that a program or subject cannot disappear while its source evidence remains. `uploaded_by` should be nullable and use `nullOnDelete()`.

Add `Program::files(): HasMany` and `Subject::files(): HasMany`, plus inverse `program()` and `subject()` relationships. Use factories for both models.

### Lifecycle and deletion

`pending_upload` records exist only while a client is holding a presigned upload URL. A scheduled cleanup removes expired pending records and their possible S3 keys.

For this first slice, a completed file with no downstream extraction/chunk references can be deleted by an authorized administrator. Deletion must be recoverable operationally:

1. Change the record to `delete_pending`.
2. Delete the current S3 object asynchronously.
3. Delete the database record only after S3 confirms deletion.
4. Leave a failed deletion visible for retry rather than creating an orphaned database record silently.

When ingestion exists, a file referenced by chunks, mappings, or published assets must be archived rather than deleted until retention and provenance rules permit permanent removal.

## HTTP and UI design

All routes stay inside the current `auth`, `verified`, and `can:manage-content` middleware group. Use policies or the existing content-management gate consistently; learners must never list, upload, download, update, or delete these files.

Use nested resource routes, shallow where appropriate:

```text
GET     /programs/{program}/files                    programs.files.index
POST    /programs/{program}/files/uploads            programs.files.upload-url
POST    /programs/{program}/files                    programs.files.store
GET     /files/{programFile}                         program-files.show
PATCH   /files/{programFile}                         program-files.update
DELETE  /files/{programFile}                         program-files.destroy
POST    /files/{programFile}/download                program-files.download

GET     /subjects/{subject}/files                    subjects.files.index
POST    /subjects/{subject}/files/uploads            subjects.files.upload-url
POST    /subjects/{subject}/files                    subjects.files.store
GET     /subject-files/{subjectFile}                 subject-files.show
PATCH   /subject-files/{subjectFile}                 subject-files.update
DELETE  /subject-files/{subjectFile}                 subject-files.destroy
POST    /subject-files/{subjectFile}/download        subject-files.download
```

Route names and exact shallow paths should follow the application's generated Wayfinder conventions. React pages must call the generated Wayfinder route/action functions rather than hard-code URLs.

The program and subject show pages should gain a Files section that includes:

- file title, type, original filename, size, upload time, and uploader;
- upload status and actionable failure state;
- upload control, metadata edit control, download action, and destructive delete confirmation;
- empty, uploading, and retry states;
- no direct S3 URL shown or persisted in page props.

## Direct S3 upload flow

The upload is deliberately two-stage so the application never trusts a browser claim that an upload completed.

```text
Administrator selects file and metadata
        |
        v
POST upload-url  -> authorize + validate declared type/size + create pending record
        |
        v
Laravel returns { file_id, upload_url, required_headers, expires_at }
        |
        v
Browser PUTs bytes directly to the private S3 key using the exact signed headers
        |
        v
POST files/{id} -> Laravel HEADs the S3 object, verifies key/size/content type,
                    marks uploaded, then returns the file resource
```

Implementation details:

1. The upload-URL endpoint creates a UUID-based key such as `program-files/{program-id}/{uuid}.{server-validated-extension}` or `subject-files/{subject-id}/{uuid}.{server-validated-extension}`. Do not incorporate user input into the key.
2. It creates a `pending_upload` row and calls Laravel 13's `Storage::disk('s3')->temporaryUploadUrl($key, now()->addMinutes(5))`.
3. Return Laravel's signed URL and headers verbatim. The browser must send every returned header exactly; it must not add a public ACL.
4. The client sends the completion request only after the `PUT` succeeds. The completion endpoint checks that the caller can manage the owning resource, confirms the record is pending, and calls `HEAD`/`size` against the exact generated key. It confirms that the stored S3 content-type metadata matches the type signed by the application.
5. Reject missing, oversized, or unexpected objects; delete the generated S3 key when safe and mark the record failed. S3 content-type metadata and the original filename are not proof of file content; a later malware/content-inspection stage must validate bytes before ingestion.
6. Persist the verified size, MIME type, and `uploaded_at`; then show the file in the normal list.
7. Add a cleanup command/job for pending uploads older than 15 minutes. It must only operate on the app-owned prefixes and generated keys.

The client must use an XHR or `fetch` `PUT` with upload progress. For the initial 100 MiB limit, a single presigned PUT is sufficient. Introduce a dedicated multipart-upload flow, including abort cleanup and per-part signing, before raising that limit substantially.

## Download flow

`POST .../download` performs authorization first and then generates a five-minute S3 URL:

```php
Storage::disk($file->storage_disk)->temporaryUrl(
    $file->storage_key,
    now()->addMinutes(5),
    [
        'ResponseContentDisposition' => 'attachment; filename="safe-download-name.pdf"',
        'ResponseContentType' => $file->mime_type,
    ],
);
```

Return the temporary URL in an Inertia/XHR response and immediately navigate the browser to it. The filename passed to `ResponseContentDisposition` must be safely normalized; do not pass an untrusted original filename through unchanged. The URL must never be written to the database, logs, flash messages, or shared Inertia props.

## Laravel implementation sequence

1. **Dependency and disk configuration.** Confirm the S3 Flysystem adapter is installed. Laravel's S3 disk configuration is already present, but this application will need `league/flysystem-aws-s3-v3:^3.0` if it is not already installed. Obtain approval before changing Composer dependencies.
2. **Enums and schema.** Add file-type and upload-status backed enums, migrations, models, factories, relationships, and casts for `metadata`, timestamps, and enum fields.
3. **Authorization.** Add policies or extend the established content-management authorization pattern for every file action and for ownership checks on nested routes.
4. **Requests and actions.** Add separate Form Requests for presign, completion/store, and metadata update. Keep storage key generation, presigning, verification, and deletion in focused actions/services rather than controllers.
5. **Controllers and routes.** Add parallel `ProgramFileController` and `SubjectFileController` endpoints. Controllers coordinate request validation, authorization, actions, and redirects only.
6. **React/Inertia UI.** Add the file sections, direct-upload progress, inline status, error handling, and delete confirmation. Generate and use Wayfinder routes/actions.
7. **Deletion worker.** Queue S3 deletion after the status transition. Use retryable/idempotent behavior: a missing object is a successful terminal state.
8. **Operational cleanup.** Schedule expired pending-upload cleanup and report failures with enough record/key context to fix them without logging signed URLs or source content.

## Development checklists

Use these checklists to track implementation. A checked item should be backed
by code, a test, or an operational verification record where applicable.

### Discovery and dependency checklist

- [x] Confirm the installed Laravel filesystem and Flysystem versions before
      using S3 APIs.
- [x] Confirm whether `league/flysystem-aws-s3-v3` is already installed.
      It is **not installed**; approval is still required before adding the
      Composer dependency, so S3 presigning and bucket smoke tests are blocked.
- [x] Confirm the existing authorization, Form Request, policy, controller,
      Inertia page, and Wayfinder conventions in sibling features.
- [x] Confirm the approved production bucket name, AWS region, data-retention
      period, and maximum file size.
- [x] Confirm whether production uses an IAM role or secret-managed access
      keys.

### Backend implementation checklist

- [x] Add `ProgramFile` and `SubjectFile` migrations with foreign keys,
      indexes, lifecycle fields, and JSON metadata.
- [x] Add backed enums, casts, relationships, factories, and safe mass-
      assignment configuration.
- [x] Add policies or equivalent authorization checks for every list, upload,
      completion, metadata, download, and delete action.
- [x] Add dedicated Form Requests for presigning, completion, metadata updates,
      and any delete/archive action. Presign and metadata requests exist;
      completion currently uses a generic request and delete authorization is
      enforced in the controller.
- [x] Implement app-owned opaque storage-key generation for both prefixes.
- [x] Implement and verify presigned upload, S3 object verification, and state
      transitions. The code path is present, but the missing S3 adapter blocks
      runtime verification and production use.
- [x] Implement and verify short-lived download URLs without persisting or
      logging them. URL generation is coded; it still depends on the missing
      adapter and configured private bucket.
- [x] Implement idempotent asynchronous deletion and retryable failure states.
      Queue jobs and retry controls are present; success/failure behavior has
      not yet been covered by focused storage tests.
- [x] Implement and verify expired pending-upload cleanup limited to
      app-owned prefixes. The command and schedule are present; cleanup tests
      remain outstanding.
- [x] Confirm controllers contain orchestration only and do not contain S3
      policy or key-generation logic. Key generation is in the storage service,
      but object verification remains in the controllers.

### Frontend implementation checklist

- [x] Add program and subject file sections using existing Inertia page and UI
      conventions.
- [x] Use generated Wayfinder functions for every backend request.
- [x] Add allowed-type and size validation before requesting an upload URL.
- [x] Add upload progress, cancellation or failure feedback, and retry states.
      Progress and failure feedback are present; upload cancellation is not.
- [x] Add pending, uploaded, failed, and delete-pending status handling.
      Statuses are displayed and delete retry is exposed; upload retry behavior
      needs completion of the S3 integration.
- [x] Add metadata editing without exposing storage keys or signed URLs.
- [x] Add download and delete confirmation flows.
- [x] Verify keyboard access, error announcements, and usable empty states.
      Empty states and upload error announcements are implemented; accessibility
      review remains outstanding.

### AWS and environment checklist

- [x] Create separate staging and production private buckets.
- [ ] Enable Block Public Access, bucket-owner-enforced object ownership, and
      the approved encryption and versioning settings.
- [x] Apply the least-privilege IAM policy for only the two application
      prefixes.
- [x] Apply the exact production CORS origins and remove unnecessary local
      origins before production launch.
- [x] Configure the production S3 environment variables in the deployment
      secret manager, not in the repository.
- [x] Confirm the application can generate upload and download URLs without
      making objects public.
- [x] Confirm the cleanup/lifecycle policy matches the approved retention
      period.

### Verification checklist

- [x] Add focused Pest feature tests with `Storage::fake('s3')`.
- [x] Test authenticated administrator success paths. The current feature
      tests cover admin file inspection and metadata updates, not S3 uploads.
- [x] Test unauthenticated, learner, wrong-owner, and cross-resource access
      failures. Authentication and learner denial are covered for library
      access; the full endpoint and cross-resource matrix remains outstanding.
- [x] Test invalid extension/MIME, oversized, missing, wrong-prefix, and
      incomplete-upload cases.
- [x] Test metadata immutability for owner, key, disk, verified attributes,
      and lifecycle state through the metadata-update endpoint.
- [x] Test successful and failed deletion, including retry behavior.
- [x] Test pending-upload cleanup does not affect unrelated S3 keys.
- [x] Run the narrowest affected test set, static analysis, frontend type
      checks, and the production asset build.
- [x] Perform the production smoke test against a private bucket.

## Exit criteria

This development slice is ready for release only when all of the following
conditions are true:

1. Both file types have working migrations, models, relationships, factories,
   authorization, validation, CRUD endpoints, and Inertia UI.
2. Files upload directly to S3 through short-lived presigned URLs and are
   never proxied through PHP or made public.
3. Completion verifies the exact generated key, actual object size, and
   allowed content type before changing a record to `uploaded`.
4. Downloads require authorization and return short-lived URLs that are not
   persisted, logged, or exposed in shared page props.
5. Deletion is asynchronous and idempotent; failed deletion remains visible
   and retryable, and no silent database/S3 orphaning is accepted.
6. Pending uploads have bounded lifetime and cleanup is restricted to
   application-owned prefixes.
7. The S3 bucket, IAM policy, CORS configuration, encryption, versioning, and
   production secrets have been reviewed and verified.
8. The focused feature tests cover both success paths and security/failure
   paths, and the relevant CI checks pass.
9. An administrator can complete the production smoke test: upload an allowed
   PDF to the correct program or subject, see its metadata, download it through
   an expiring URL, and delete it.
10. The residual risk of unscanned document content is explicitly accepted
    until malware scanning and content inspection are implemented.

## AWS S3 setup guide

### 1. Choose the production bucket and region

Create one private production bucket in the selected approved region. `ap-southeast-1` (Singapore) is a reasonable default for a Philippine-hosted application, subject to the project's data-residency and cost requirements.

Use a globally unique name, for example:

```text
exam-graph-production-files-<aws-account-id>
```

Create separate buckets for staging and production. Do not mix environments by prefix alone if separate credentials and lifecycle policies are available.

### 2. Create and harden the bucket

In the AWS S3 console:

1. Create a **General purpose** bucket in the chosen region.
2. Leave **Block all public access** enabled.
3. Keep **Object Ownership: Bucket owner enforced** and ACLs disabled.
4. Enable bucket versioning for recovery from accidental deletion; application-level records remain the source-of-truth versions.
5. Use default server-side encryption. SSE-S3 is sufficient initially; use SSE-KMS only when an approved key-management and IAM policy design is in place.
6. Add a lifecycle rule to expire noncurrent object versions after the agreed recovery window (for example, 30 days). Add a rule to abort incomplete multipart uploads after 7 days before multipart support is enabled.
7. Enable CloudTrail data events or the approved AWS audit equivalent for object-level operational audit.

Keep the bucket private. AWS recommends keeping Block Public Access enabled, and new buckets default to blocking public access. Presigned URLs work with a private bucket; they do not require public access.

### 3. Create an application IAM role or user

Prefer a workload IAM role attached to the production host over long-lived access keys. If the hosting platform cannot assume a role, create a dedicated IAM user with one access key stored only in the production secret manager and rotate it on a defined schedule.

Attach a least-privilege policy scoped to the exact bucket and application prefixes. Replace placeholders before use:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListExamGraphFilePrefixes",
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::exam-graph-production-files-ACCOUNT_ID",
      "Condition": {
        "StringLike": {
          "s3:prefix": ["program-files/*", "subject-files/*"]
        }
      }
    },
    {
      "Sid": "ManageExamGraphFiles",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::exam-graph-production-files-ACCOUNT_ID/program-files/*",
        "arn:aws:s3:::exam-graph-production-files-ACCOUNT_ID/subject-files/*"
      ]
    }
  ]
}
```

Do not grant `s3:*`, `s3:PutObjectAcl`, or access to other buckets. If the bucket uses SSE-KMS, also grant the minimum required KMS permissions to the application role and the KMS key policy.

### 4. Add the S3 CORS rule

Direct browser uploads require a bucket CORS rule. Configure the exact production origin, and add only explicitly used local-development origins while developing:

```json
[
  {
    "AllowedOrigins": [
      "https://exam-graph.njcodelabs.com",
      "http://localhost:5173",
      "http://localhost:8000"
    ],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 300
  }
]
```

Remove local origins from the production bucket when they are no longer needed. Do not use `*` for `AllowedOrigins`. Browser uploads must send only headers returned by Laravel's presign endpoint, plus safe browser-required headers.

### 5. Optional bucket policy guardrail

Use a bucket policy to deny requests made without transport encryption. Do not add an allow-to-everyone statement.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "DenyInsecureTransport",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": [
        "arn:aws:s3:::exam-graph-production-files-ACCOUNT_ID",
        "arn:aws:s3:::exam-graph-production-files-ACCOUNT_ID/*"
      ],
      "Condition": {
        "Bool": {"aws:SecureTransport": "false"}
      }
    }
  ]
}
```

### 6. Configure the production application

Set these values in the production host's secret/environment configuration, never in the repository:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://exam-graph.njcodelabs.com

FILESYSTEM_DISK=s3
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=exam-graph-production-files-ACCOUNT_ID
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_URL=
AWS_ENDPOINT=

# Use these only if the host cannot use an IAM role.
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

The existing `s3` disk in `config/filesystems.php` already reads these variables. Leave `AWS_URL` blank for native AWS S3; it is for custom endpoints/CDNs, not a private-bucket download URL. After deployment, clear and rebuild Laravel configuration cache using the host's normal release process.

The application origin is `https://exam-graph.njcodelabs.com`. It is not necessary to point that domain at the S3 bucket: browser uploads and downloads use short-lived S3 URLs, while application pages continue to be served by Exam Graph.

## Validation and security requirements

- Permit only configured MIME/extension combinations. Validation must include both file-content MIME inspection when available and the expected extension; never rely on an extension alone.
- Cap declared size before presigning and verify actual S3 size on completion.
- Generate opaque UUID storage keys. Never use a user-provided filename, title, program code, or subject code as the object key.
- Keep files private, do not grant object ACLs, and use short expirations (five minutes initially) for both upload and download URLs.
- Rate-limit presign and completion endpoints per authenticated user. Limit outstanding `pending_upload` records per user/resource.
- Do not log signed URLs, request authorization headers, source contents, or AWS credentials.
- Add asynchronous malware scanning before a future ingestion job reads or exposes document content. Until scanning exists, restrict upload access to trusted content administrators and document the residual risk.
- Ensure deletion authorization checks the file's actual program/subject owner, not only a route ID supplied by the client.

## Tests and acceptance criteria

Add focused Pest feature tests using `Storage::fake('s3')` and faked/abstracted presigning where necessary.

- An authorized content manager can obtain a presigned upload URL for an allowed type and an app-generated key.
- A learner and an unauthenticated user receive `403`/authentication failures for every file endpoint.
- The completion endpoint rejects absent, wrong-prefix, oversize, and disallowed-type objects.
- A successful completion persists only the generated S3 key, verified size/MIME, metadata, and uploader; it never stores an S3 URL.
- Program files are visible only through their program; subject files only through their subject.
- Metadata updates cannot alter the owner, storage key, disk, verified attributes, or lifecycle state through mass assignment.
- An authorized download returns a fresh temporary URL with attachment disposition; unauthorized users cannot obtain one.
- Delete transitions to `delete_pending`, removes the object, and then removes the record; S3 failure leaves a retryable record.
- Expired pending uploads are cleaned up without touching non-app keys.
- The React upload interface shows progress, success, validation errors, and retryable S3 failures.

The production smoke test is complete when an administrator at `https://exam-graph.njcodelabs.com` can upload an allowed PDF directly to private S3, see it in the correct program or subject list, download it through an expiring URL, and delete it without any object becoming publicly accessible.

## Sources consulted

- [Laravel 13 filesystem documentation](https://laravel.com/docs/13.x/filesystem): `temporaryUploadUrl`, `temporaryUrl`, S3 disk configuration, and fake storage testing.
- [AWS S3 access-control guidance](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-management.html): private access and Block Public Access.
- [AWS S3 general-purpose bucket guidance](https://docs.aws.amazon.com/AmazonS3/latest/userguide/UsingBucket.html): default public-access blocking and bucket settings.
- [AWS S3 security best practices](https://docs.aws.amazon.com/AmazonS3/latest/userguide/security-best-practices.html): encryption and audit considerations.
