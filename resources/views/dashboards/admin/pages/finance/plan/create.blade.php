@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ isset($plan) ? 'Edit' : 'Create' }} Plan</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Plans</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($plan) ? 'Edit' : 'Create' }}</li>
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
                        <form action="{{ isset($plan) ? route('admin.plans.update', $plan->id) : route('admin.plans.store') }}" method="POST" enctype="multipart/form-data"> @csrf
                            @isset($plan)
                                @method('put')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Name</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="name" id="input-placeholder" value="{{ old('name') ?? ($plan->name ?? '') }}" placeholder="Enter plan name">
                                    </div>
                                </div>
                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Description</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="description" id="input-placeholder" value="{{ old('description') ?? ($plan->description ?? '') }}" placeholder="Enter plan description">
                                    </div>
                                </div>
                                <div class="row col-xl-10 col-sm-12">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('status') ?? ($plan->status ?? '')) == $key ? 'selected' : '' }}>
                                                    {{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <hr class="mt-4">
                            <div class="col-xl-10 col-sm-12 d-flex mb-4 align-items-center justify-content-between ">
                                Add Duration Prices
                                <div class="row col-xl-10 col-sm-12 addPlanDurationDiv">
                                    <div class="col-xl-3 col-lg-3 col-md-3 col-sm-2"></div>
                                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 d-flex justify-content-end">
                                        <button type="button" class="btn btn-outline-success btn-sm addPlanDurationItem">
                                            Add New Price
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @if (isset($plan) && $plan->durations->isNotEmpty())
                                <div class="" id="planDuration">
                                    @foreach ($plan->durations as $plan_duration)
                                        <div class="plan-duration-section mt-4">
                                            <div class="row col-xl-10 col-sm-12 mb-3">
                                                <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Frequency</label>
                                                <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                    <select name="frequency[]" id="" class="form-control">
                                                        <option value="" disabled selected>Select Option</option>
                                                        @foreach ($frequencyOptions as $key => $value)
                                                            <option value="{{ $key }}" {{ $plan_duration->frequency == $key ? 'selected' : '' }}>
                                                                {{ $value }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row col-xl-10 col-sm-12 mb-3">
                                                <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Price
                                                    (USD)
                                                </label>
                                                <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                    <input type="number" class="form-control" name="price[]" value="{{ $plan_duration->price }}" id="input-placeholder" placeholder="Enter price">
                                                </div>
                                            </div>
                                            <div class="row col-xl-10 col-sm-12 mb-3">
                                                <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Discount
                                                    (%)</label>
                                                <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                    <input type="number" class="form-control" name="discount[]" value="{{ $plan_duration->discount }}" id="contact-input" placeholder="Enter plan discount" oninput="validateDiscount(this)">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="" id="planDuration">
                                    <div class="plan-duration-section">
                                        <div class="row col-xl-10 col-sm-12 mb-3 frequencyDiv">
                                            <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Frequency</label>
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <select name="frequency[]" id="" class="form-control">
                                                    <option value="" disabled selected>Select Option</option>
                                                    @foreach ($frequencyOptions as $key => $value)
                                                        <option value="{{ $key }}">{{ $value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row col-xl-10 col-sm-12 mb-3">
                                            <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Price (USD)</label>
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <input type="number" class="form-control" name="price[]" id="input-placeholder" placeholder="Enter price">
                                            </div>
                                        </div>
                                        <div class="row col-xl-10 col-sm-12 mb-3">
                                            <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Discount (%)</label>
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <input type="number" class="form-control" name="discount[]" oninput="validateDiscount(this)" id="contact-input" placeholder="Enter plan discount" min="1" max="100">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            @endif

                            <hr class="mt-4">
                            <div class="mb-3">
                                Enabled Features
                            </div>
                            <div class="gy-4 mb-4">
                                @foreach ($scopeOptions ?? [] as $key => $scope)
                                    @if ($scope['type'] == 'number')
                                        <div class="row col-xl-10 col-sm-12 mb-3">
                                            <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">{{ $scope['label'] }}</label>
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <input type="{{ $scope['type'] }}" class="form-control" name="scopes[{{ $scope['name'] }}]" id="input-placeholder" value="{{ isset($plan_scopes) ? $plan_scopes->where("title", $scope["name"])->first()->value : null }}" placeholder="{{ $scope['placeholder'] }}">
                                            </div>
                                        </div>
                                    @elseif ($scope['type'] == 'checkbox')
                                        <div class="row col-xl-10 col-sm-12 mb-3">
                                            <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">{{ $scope['label'] }}</label>
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <select name="scopes[{{ $scope['name'] }}]" id="" class="form-control">
                                                    <option value="" disabled selected>Select Option</option>
                                                    @foreach ($scope['data'] ?? null as $scope_key => $scope_data)
                                                        <option value="{{ $scope_key }}" {{ (isset($plan_scopes) && in_array(($plan_scopes->where("title", $scope["name"])->first()->value), [$scope_key])) ? "selected" : "" }}>{{ $scope_data }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            {{-- <hr class="mt-4">
                            <div class="">
                                Add Benefits of this plan
                            </div>
                            <div class="mb-4">
                                @php
                                    $benefit_keys = $benefits?->pluck('key')?->toArray();
                                @endphp
                                @foreach ($featuresOptions ?? [] as $key => $value)
                                    <div class="row col-xl-4 col-sm-12 mt-3 align-items-center">
                                        <div class="col-2">
                                            <input type="checkbox" class="form-check" name="benefits[{{ $key }}]" {{ in_array($key, $benefit_keys) ? 'checked' : '' }} id="" style="width: 20px; height:20px">
                                        </div>
                                        <div class="col-10">
                                            {{ $value }}
                                        </div>
                                    </div>
                                @endforeach
                            </div> --}}
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
        <!-- End:: row-1 -->
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script>
        $(".addPlanDurationItem").on("click", function() {
            const sectionClone = $(".plan-duration-section:first").clone();
            sectionClone.find("input").val("");

            sectionClone.prepend('<div class="btn btn-sm mt-3"></div>');
            sectionClone.append(
                '<label class=""><button type="button" class="btn btn-sm mb-3 btn-outline-danger btn-md remove-section">Remove</button></label>'
            );
            $("#planDuration").append(sectionClone);
        });
        // Function to remove a logistics section
        $("#planDuration").on("click", ".remove-section", function() {
            $(this).closest(".plan-duration-section").remove();
        });
    </script>
    <script>
        function validateDiscount(input) {
            if (input.value > 100) {
                input.value = 100;
            }
        }
    </script>
@endsection
