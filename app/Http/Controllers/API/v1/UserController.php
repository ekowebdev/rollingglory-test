<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Services\UserService;
use App\Http\Resources\DeletedResource;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\BaseController;
use App\Http\Resources\UserResource;

class UserController extends BaseController
{
    private $service;

    public function __construct(UserService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index($locale)
    {
        $data = $this->service->index($locale, Request::all());
        return (UserResource::collection($data))
                ->additional([
                    'sortableAndSearchableColumn' => $data->sortableAndSearchableColumn,
                ]);
    }

    public function show($locale, $id)
    {
        $data = $this->service->show($locale, $id);
        return new UserResource($data);
    }

    public function store($locale)
    {
        $data = $this->service->store($locale, Request::all());
        return new UserResource($data);
    }

    public function update($locale, $id)
    {
        $data = $this->service->update($locale, $id, Request::all());
        return new UserResource($data);
    }

    public function delete($locale, $id)
    {
        $data = $this->service->delete($locale, $id, Request::all());
        return new DeletedResource($data);
    }

    public function setMainAddress($locale)
    {
        $data = $this->service->setMainAddress($locale, Request::all());
        return new UserResource($data);
    }
}
