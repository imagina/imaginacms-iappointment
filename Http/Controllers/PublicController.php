<?php
namespace Modules\Iappointment\Http\Controllers;

use Modules\Iappointment\Entities\Appointment;
use Modules\Iappointment\Repositories\CategoryRepository;
use Modules\Ihelpers\Http\Controllers\Api\BaseApiController;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicController extends BaseApiController
{
    private $category;
    private $plan;
    private $subscriptionService;
    private $appointmentService;

    public function __construct(CategoryRepository $category)
    {
        $this->category = $category;
        $this->plan = app('Modules\Iplan\Repositories\PlanRepository');
        $this->subscriptionService = app('Modules\Iplan\Services\SubscriptionService');
        $this->appointmentService = app('Modules\Iappointment\Services\AppointmentService');
    }

    public function indexCategory(Request $request){
        $tpl = 'iappointment::frontend.category.index';
        $ttpl = 'iappointment.category.index';

        if (view()->exists($ttpl)) $tpl = $ttpl;

        $params = $this->getParamsRequest($request);

        $categories = $this->category->getItemsBy($params);

        if(!$categories)
            return abort(404);

        return view($tpl, compact('categories'));

    }

    public function showCategory($criteria, Request $request){
        if(!auth()->check()){
            return redirect("/ipanel/#/auth/login"."?redirectTo=".$request->url());
        }
        $params = $this->getParamsRequest($request);
        $category = $this->category->getItem($criteria, $params);

        request()->session()->put('category_id',$criteria);

        $subscriptionValidate = $this->subscriptionService->validate(new Appointment());

        $locale = \LaravelLocalization::setLocale() ?: \App::getLocale();

        if($subscriptionValidate){
            $appointment = $this->appointmentService->create($criteria);
            return redirect("/ipanel/#/appointment/{$appointment->id}");
        }
        return redirect()->route($locale . '.iplan.plan.index');
    }

}
