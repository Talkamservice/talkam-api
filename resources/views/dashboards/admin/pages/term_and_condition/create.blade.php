@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($term_and_condition) ? 'Edit' : 'Create' }} Term and Condition</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.terms-and-conditions.index') }}">Term and Condition</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($term_and_condition) ? 'Edit' : 'Create' }}</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->
        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="{{ route("admin.terms-and-conditions.store") }}" method="POST" enctype="multipart/form-data"> @csrf
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-12 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Terms Of Use</label>
                                    <div class="col-xl- col-lg-8 col-md-8 col-sm-12">
                                        <textarea name="body" class="form-control" id="mytextarea" cols="30" rows="2">{{ old('body') ?? ($term_and_condition->body ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer d-none border-top-0">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        classicEditorInit('#mytextarea');
    </script>
@endsection

