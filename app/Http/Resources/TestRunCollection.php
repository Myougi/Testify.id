<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TestRunCollection extends ResourceCollection
{
    /** @var class-string<TestRunResource> */
    public $collects = TestRunResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) parent::toArray($request);
    }
}
