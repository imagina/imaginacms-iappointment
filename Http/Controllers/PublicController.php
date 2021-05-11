<?php
namespace Modules\Iappointment\Http\Controllers;

use Modules\Iappointment\Repositories\CategoryRepository;
use Modules\Ihelpers\Http\Controllers\Api\BaseApiController;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicController extends BaseApiController
{
    private $category;

    public function __construct(CategoryRepository $category)
    {
        $this->category = $category;
    }

    public function showCategory($criteria, Request $request){
        $params = $this->getParamsRequest($request);
        $category = $this->category->getItem($criteria, $params);
        request()->session()->put('category_id',$criteria);

        $locale = \LaravelLocalization::setLocale() ?: \App::getLocale();
        return redirect()->route($locale . '.iplan.plan.index');
    }

}
