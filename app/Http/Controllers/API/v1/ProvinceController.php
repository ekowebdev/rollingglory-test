<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Services\ProvinceService;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\BaseController;
use App\Http\Resources\ProvinceResource;

class ProvinceController extends BaseController
{
    private $service;

    public function __construct(ProvinceService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index($locale)
    {
        $data = $this->service->getIndexData($locale, Request::all());
        return (ProvinceResource::collection($data))
                ->additional([
                    'sortable_and_searchable_column' => $data->sortableAndSearchableColumn,
                ]);
    }

    public function show($locale, $id)
    {
        $data = $this->service->getSingleData($locale, $id);
        return new ProvinceResource($data);
    }
}
