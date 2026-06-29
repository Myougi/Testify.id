<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TestCaseCollection extends ResourceCollection
{
    /** @var class-string<TestCaseResource> */
    public $collects = TestCaseResource::class;

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
