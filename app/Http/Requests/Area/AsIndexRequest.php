<?php

namespace App\Http\Requests\Area;

use App\Exceptions\EmptyResultException;
use App\Models\Admin\Area;
use App\Models\Admin\Country;
use Illuminate\Validation\Validator;

Trait AsIndexRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function withValidator(Validator $validator)
    {
        if(!$validator->fails()){
            $this->country = Country::findOrFail($this->country_id);
            if($this->state_name){
                $code = $this->country->simple_code;
                $this->state = Area::state()->where('country_code', $code)->where('name', $this->state_name)->first();

                if(!$this->state) throw new EmptyResultException($this->state_name. ' state not found');
            }
    
            if($this->city_name){
                $this->city = Area::city()->where('parent_id', $this->state->id)->where('name', $this->city_name)->first();

                if(!$this->city) throw new EmptyResultException($this->city_name. ' city not found');
            }
    
            if($this->district_name){
                $this->district = Area::district()->where('parent_id', $this->city->id)->where('name', $this->district_name)->first();

                if(!$this->district) throw new EmptyResultException($this->district_name.' district not found');
            }
        }
        
    }

}
