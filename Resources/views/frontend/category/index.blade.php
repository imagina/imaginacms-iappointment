@extends('layouts.master')
@section('content')
    <div class="page appointment-category-index py-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <livewire:isite::items-list
                            moduleName="Iappointment"
                            itemComponentName="isite::item-list"
                            itemLayout="item-list-layout-1"
                            entityName="Category"
                            :showTitle="false"
                            :responsiveTopContent="['mobile'=>false,'desktop'=>false]"
                            :itemComponentAttributes="[
                                'itemLayout' => 'item-list-layout-1',
                                'withViewMoreButton' => true,
                                'viewMoreButtonLabel' => 'Ir a Consulta',
                            ]"
                    />
                </div>
            </div>
        </div>
    </div>
@endsection
