<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Services\SubdistrictService;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\BaseController;
use App\Http\Resources\SubdistrictResource;

class SubdistrictController extends BaseController
{
    private $service;

    public function __construct(SubdistrictService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index($locale)
    {
        $data = $this->service->index($locale, Request::all());
        return (SubdistrictResource::collection($data))
                ->additional([
                    'sortableAndSearchableColumn' => $data->sortableAndSearchableColumn,
                ]);
    }

    public function show($locale, $id)
    {
        $data = $this->service->show($locale, $id);
        return new SubdistrictResource($data);
    }
}
