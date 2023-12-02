<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'redeems' => [
                'redeem_id' => $this->redeems->id,
                'redeem_code' => $this->redeems->redeem_code,
                'total_point' => $this->redeems->total_point,
                'redeem_date' => $this->redeems->redeem_date,
                'redeem_date' => Carbon::parse($this->redeems->created_at)->format('Y-m-d H:i:s'),
                'fredeem_date' => Carbon::parse($this->redeems->created_at)->diffForHumans(),
                'note' => $this->redeems->note,
                'snap_token' => $this->redeems->snap_token,
                'snap_url' => $this->redeems->snap_url,
                'metadata' => json_decode($this->redeems->metadata),
                'redeem_status' => $this->redeems->redeem_status,
                'redeem_item_gifts' => $this->redeems->redeem_item_gifts->map(function ($redeem_item_gift){
                    return [
                        'redeem_id' => $redeem_item_gift->redeem_id,
                        'redeem_quantity' => $redeem_item_gift->redeem_quantity,
                        'redeem_point' => $redeem_item_gift->redeem_point,
                        'item_gifts' => [
                            'id' => $redeem_item_gift->item_gifts->id,
                            'item_gift_code' => $redeem_item_gift->item_gifts->item_gift_code,
                            'item_gift_name' => $redeem_item_gift->item_gifts->item_gift_name,
                            'item_gift_slug' => $redeem_item_gift->item_gifts->item_gift_slug,
                            'category' => ($redeem_item_gift->item_gifts->category_id != null) ? $redeem_item_gift->item_gifts->category->makeHidden(['created_at', 'updated_at']) : null,
                            'brand' => ($redeem_item_gift->item_gifts->brand_id != null) ? $redeem_item_gift->item_gifts->brand->makeHidden(['created_at', 'updated_at']) : null,
                            'item_gift_description' => $redeem_item_gift->item_gifts->item_gift_description,
                            'item_gift_spesification' => json_decode($redeem_item_gift->item_gifts->item_gift_spesification) ?? [],
                            'item_gift_point' => $redeem_item_gift->item_gifts->item_gift_point ?? 0,
                            'fitem_gift_point' => $this->format_item_gift_point($redeem_item_gift),
                            'item_gift_weight' => $redeem_item_gift->item_gifts->item_gift_weight ?? 0,
                            'fitem_gift_weight' => $this->format_item_gift_weight($redeem_item_gift),
                            'item_gift_status' => $redeem_item_gift->item_gifts->item_gift_status,
                            'item_gift_images' => $redeem_item_gift->item_gifts->item_gift_images->map(function ($image) {
                                return [
                                    'item_gift_id' => $image->item_gift_id,
                                    'variant_id' => $image->variant_id,
                                    'item_gift_image_url' => $image->item_gift_image_url,
                                    'item_gift_image_thumbnail_url' => $image->item_gift_image_thumb_url,
                                ];
                            }),
                        ],
                        'variants' => ($redeem_item_gift->variants) 
                            ? [
                                'id' => $redeem_item_gift->variants->id,
                                'variant_name' => $redeem_item_gift->variants->variant_name,
                                'variant_slug' => $redeem_item_gift->variants->variant_slug,
                                'variant_quantity' => $redeem_item_gift->variants->variant_quantity,
                                'variant_point' => $redeem_item_gift->variants->variant_point,
                                'fvariant_point' => format_money(strval($redeem_item_gift->variants->variant_point)),
                                'variant_weight' => $redeem_item_gift->variants->variant_weight,
                                'fvariant_weight' => $redeem_item_gift->variants->variant_weight . ' Gram',
                                'variant_image' => ($redeem_item_gift->variants->item_gift_images) ? [
                                    'id' => $redeem_item_gift->variants->item_gift_images->id,
                                    'image' => $redeem_item_gift->variants->item_gift_images->item_gift_image,
                                    'image_url' => $redeem_item_gift->variants->item_gift_images->item_gift_image_url,
                                    'image_thumb_url' => $redeem_item_gift->variants->item_gift_images->item_gift_image_thumb_url,
                                ] : null,
                            ] : null,
                    ];
                })
            ],
            'shipping_origin' => [
                'id' => $this->city_origin->city_id,
                'city_name' => $this->city_origin->city_name
            ],
            'shipping_destination' => [
                'id' => $this->city_destination->city_id,
                'city_name' => $this->city_destination->city_name
            ],
            'shipping_weight' => $this->weight,
            'shipping_fweight' => $this->weight . ' Gram',
            'shipping_courier' => $this->courier,
            'shipping_service' => $this->service,
            'shipping_description' => $this->description,
            'shipping_cost' => $this->cost,
            'shipping_etd' => $this->etd,
            'shipping_resi' => $this->resi,
            'shipping_status' => $this->status,
        ];
    }

    private function format_item_gift_weight($item)
    {
        $variant_weight = $item->item_gifts->variants->pluck('variant_weight')->toArray();
        if (count($variant_weight) == 1) {
            return strval($variant_weight[0]) . ' Gram';
        } elseif (count($variant_weight) > 1) {
            $variant_weight = min($variant_weight);
            return strval($variant_weight) . ' Gram';
        } else {
            return strval($item->item_gifts->item_gift_weight ?? 0) . ' Gram';
        }
    }

    private function format_item_gift_point($item)
    {
        $variant_points = $item->item_gifts->variants->pluck('variant_point')->toArray();
        
        if (count($variant_points) == 1) {
            return strval($variant_points[0]);
        } elseif (count($variant_points) > 1) {
            $min_value = min($variant_points);
            $max_value = max($variant_points);

            if ($min_value === $max_value) {
                return strval($min_value);
            }

            return format_money($min_value) . " ~ " . format_money($max_value);
        } else {
            return format_money(strval($item->item_gifts->item_gift_point ?? 0));
        }
    }
}
