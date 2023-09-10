<?php

namespace App\Http\Services;

use App\Http\Models\Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Repositories\AddressRepository;

class AddressService extends BaseService
{
    private $model, $repository;

    public function __construct(Address $model, AddressRepository $repository)
    {
        $this->model = $model;
        $this->repository = $repository;
    }

    public function getIndexData($locale, $data)
    {
        $search = [
            'user_id' => 'user_id',
            'province_id' => 'province_id',
            'city_id' => 'city_id',
            'postal_code' => 'postal_code',
            'address' => 'address',
        ];

        $search_column = [
            'id' => 'id',
            'user_id' => 'user_id',
            'province_id' => 'province_id',
            'city_id' => 'city_id',
            'postal_code' => 'postal_code',
            'address' => 'address',
        ];

        $sortable_and_searchable_column = [
            'search'        => $search,
            'search_column' => $search_column,
            'sort_column'   => array_merge($search, $search_column),
        ];
        
        return $this->repository->getIndexData($locale, $sortable_and_searchable_column);
    }

    public function getSingleData($locale, $id)
    {
        return $this->repository->getSingleData($locale, $id);
    }

    public function store($locale, $data)
    {
        $data_request = Arr::only($data, [
            'user_id',
            'province_id',
            'city_id',
            'district_id',
            'postal_code',
            'address',
        ]);

        $this->repository->validate($data_request, [
                'user_id' => [
                    'required',
                    'exists:users,id',
                    'unique:addresses,user_id',
                ],
                'province_id' => [
                    'required',
                    'integer',
                ],
                'city_id' => [
                    'required',
                    'integer',
                ],
                'district_id' => [
                    'nullable',
                    'integer',
                ],
                'postal_code' => [
                    'required',
                    'numeric',
                ],
                'address' => [
                    'required',
                    'string',
                ],
            ]
        );

        DB::beginTransaction();
        $result = $this->model->create($data_request);
        DB::commit();

        return $this->repository->getSingleData($locale, $result->id);
    }

    public function update($locale, $id, $data)
    {
        $check_data = $this->repository->getSingleData($locale, $id);

        $data = array_merge([
            'user_id' => $check_data->user_id,
            'province_id' => $check_data->province_id,
            'city_id' => $check_data->city_id,
            'district_id' => $check_data->district_id,
            'postal_code' => $check_data->postal_code,
            'address' => $check_data->address,
        ], $data);

        $data_request = Arr::only($data, [
            'user_id',
            'province_id',
            'city_id',
            'district_id',
            'postal_code',
            'address',
        ]);

        $this->repository->validate($data_request, [
            'user_id' => [
                'exists:users,id',
                'unique:addresses,user_id,' . $id,
            ],
            'province_id' => [
                'integer',
            ],
            'city_id' => [
                'integer',
            ],
            'district_id' => [
                'nullable',
                'integer',
            ],
            'postal_code' => [
                'numeric',
            ],
            'address' => [
                'string',
            ],
        ]);

        DB::beginTransaction();
        $check_data->update($data_request);
        DB::commit();

        return $this->repository->getSingleData($locale, $id);
    }

    public function delete($locale, $id)
    {
        $check_data = $this->repository->getSingleData($locale, $id);
        DB::beginTransaction();
        $result = $check_data->delete();
        DB::commit();

        return $result;
    }
}