<?php

namespace App\Concerns\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Route;

trait HasCurriculumRoutes
{
    /** @return array<string, int> */
    abstract public function curriculumRouteParameters(): array;

    protected function routeParameters(): Attribute
    {
        return Attribute::get(fn (): array => $this->curriculumRouteParameters());
    }

    public function curriculumRoute(string $name): string
    {
        $parameters = $this->curriculumRouteParameters();
        if (isset($parameters['concept']) && ! isset($parameters['topic']) && ! str_starts_with($name, 'subjects.')) {
            $name = 'unassigned.'.$name;
        }

        $route = Route::getRoutes()->getByName($name);

        return route($name, array_intersect_key($parameters, array_flip($route->parameterNames())));
    }
}
