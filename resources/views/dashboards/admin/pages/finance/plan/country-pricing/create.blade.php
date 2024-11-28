@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ isset($country_plan) ? 'Edit ' : 'Create ' }}Country Plan</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.country-plan-pricings.index') }}">Country
                                Plans</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($country_plan) ? 'Edit' : 'Create' }}
                        </li>
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
                        <form
                            action="{{ isset($country_plan) ? route('admin.country-plan-pricings.update', $country_plan->id) : route('admin.country-plan-pricings.store') }}"
                            method="POST" enctype="multipart/form-data"> @csrf
                            @isset($country_plan)
                                @method('put')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="country-select"
                                        class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Choose Country</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <!-- Search box -->
                                        <input type="text" id="country-search" class="form-control mb-2"
                                            placeholder="Search for a country"
                                            {{ isset($country_plan) ? 'disabled' : '' }} />

                                        <!-- Dropdown for countries -->
                                        <select name="country_id" id="country-select" class="form-control"
                                            {{ isset($country_plan) ? 'disabled' : '' }}>
                                            <option value="" disabled selected>Select Country</option>
                                            <!-- Existing countries will be populated here initially -->
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    {{ (old('country_id') ?? ($country_plan->country_id ?? '')) == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                            @if (isset($country_plan))
                                                <input type="hidden" name="country_id"
                                                    value="{{ $country_plan->country_id }}">
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="input-placeholder"
                                        class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Lowered (USD)</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" class="form-control" name="lowered_cost"
                                            id="input-placeholder"
                                            value="{{ old('lowered_cost') ?? ($country_plan->lowered_cost ?? '') }}"
                                            placeholder="Enter New Amount">
                                    </div>
                                </div>
                                <div class="row col-xl-10 col-sm-12">
                                    <label for="input-placeholder"
                                        class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}"
                                                    {{ (old('status') ?? ($country_plan->status ?? '')) == $key ? 'selected' : '' }}>
                                                    {{ $value }}</option>
                                            @endforeach
                                        </select>
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
        <!-- End:: row-1 -->
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
        crossorigin="anonymous"></script>
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
    <script>
        $(document).ready(function() {
            // When user types in the search box
            $('#country-search').on('input', function() {
                var query = $(this).val(); // Get the search query

                // If query is not empty, perform AJAX search
                if (query.length >= 2) { // Start searching after 2 characters
                    $.ajax({
                        url: "{{ route('admin.search-countries') }}", // Route to search countries
                        method: 'GET',
                        data: {
                            q: query // Send the search query
                        },
                        success: function(data) {
                            // Clear the current options in the dropdown
                            $('#country-select').empty().append(
                                '<option value="" disabled selected>Select Country</option>'
                                );

                            // Populate dropdown with new results
                            if (data.length > 0) {
                                data.forEach(function(country) {
                                    $('#country-select').append('<option value="' +
                                        country.id + '">' + country.name +
                                        '</option>');
                                });
                            } else {
                                // If no results found
                                $('#country-select').append(
                                    '<option value="" disabled>No countries found</option>');
                            }
                        },
                        error: function() {
                            // Handle error
                            alert('An error occurred while fetching countries.');
                        }
                    });
                } else {
                    // If input is less than 2 characters, clear the dropdown
                    $('#country-select').empty().append(
                        '<option value="" disabled selected>Select Country</option>');
                }
            });
        });
    </script>
@endsection
