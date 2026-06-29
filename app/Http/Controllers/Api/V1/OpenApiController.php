<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class OpenApiController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(config('api.docs_enabled'), 404);

        $contents = file_get_contents(base_path('docs/openapi/v1.yaml'));
        abort_if($contents === false, 404);

        return response(
            $contents,
            200,
            ['Content-Type' => 'application/yaml'],
        );
    }
}
