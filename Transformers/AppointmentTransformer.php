<?php

namespace Modules\Iappointment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iprofile\Transformers\UserTransformer;

class AppointmentTransformer extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'description' => $this->description ?? '',
            'categoryId' => (int)$this->category_id,
            'customerId' => (int)$this->customer_id,
            'assignedTo' => (int)$this->assigned_to,
            'options' =>  $this->options,
            'createdAt' => $this->when($this->created_at, $this->created_at),
            'updatedAt' => $this->when($this->updated_at, $this->updated_at),
            'category' => new CategoryTransformer ($this->whenLoaded('category')),
            'customer' => new UserTransformer($this->whenLoaded('customer')),
            'assigned' => new UserTransformer($this->whenLoaded('assigned')),
        ];

        $filter = json_decode($request->filter);

        // Return data with available translations
        if (isset($filter->allTranslations) && $filter->allTranslations) {
            // Get langs avaliables
            $languages = \LaravelLocalization::getSupportedLocales();

            foreach ($languages as $lang => $value) {
                $data[$lang]['description'] = $this->hasTranslation($lang) ?
                    $this->translate("$lang")['description'] ?? '' : '';
            }
        }
        return $data;
    }
}
