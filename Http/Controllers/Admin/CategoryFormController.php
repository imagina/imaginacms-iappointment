<?php

namespace Modules\Iappointment\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Iappointment\Entities\CategoryForm;
use Modules\Iappointment\Http\Requests\CreateCategoryFormRequest;
use Modules\Iappointment\Http\Requests\UpdateCategoryFormRequest;
use Modules\Iappointment\Repositories\CategoryFormRepository;
use Modules\Core\Http\Controllers\Admin\AdminBaseController;

class CategoryFormController extends AdminBaseController
{
    /**
     * @var CategoryFormRepository
     */
    private $categoryform;

    public function __construct(CategoryFormRepository $categoryform)
    {
        parent::__construct();

        $this->categoryform = $categoryform;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //$categoryforms = $this->categoryform->all();

        return view('iappointment::admin.categoryforms.index', compact(''));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('iappointment::admin.categoryforms.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  CreateCategoryFormRequest $request
     * @return Response
     */
    public function store(CreateCategoryFormRequest $request)
    {
        $this->categoryform->create($request->all());

        return redirect()->route('admin.iappointment.categoryform.index')
            ->withSuccess(trans('core::core.messages.resource created', ['name' => trans('iappointment::categoryforms.title.categoryforms')]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  CategoryForm $categoryform
     * @return Response
     */
    public function edit(CategoryForm $categoryform)
    {
        return view('iappointment::admin.categoryforms.edit', compact('categoryform'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  CategoryForm $categoryform
     * @param  UpdateCategoryFormRequest $request
     * @return Response
     */
    public function update(CategoryForm $categoryform, UpdateCategoryFormRequest $request)
    {
        $this->categoryform->update($categoryform, $request->all());

        return redirect()->route('admin.iappointment.categoryform.index')
            ->withSuccess(trans('core::core.messages.resource updated', ['name' => trans('iappointment::categoryforms.title.categoryforms')]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  CategoryForm $categoryform
     * @return Response
     */
    public function destroy(CategoryForm $categoryform)
    {
        $this->categoryform->destroy($categoryform);

        return redirect()->route('admin.iappointment.categoryform.index')
            ->withSuccess(trans('core::core.messages.resource deleted', ['name' => trans('iappointment::categoryforms.title.categoryforms')]));
    }
}
