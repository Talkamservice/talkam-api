@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ isset($promotion_pricing) ? 'Edit ' : 'Create ' }}Promotion Pricing
            </h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.promotion-pricings.index') }}">Promotion
                                Pricing</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($promotion_pricing) ? 'Edit' : 'Create' }}
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
                        <form action="{{ isset($promotion_pricing) ? route('admin.promotion-pricings.update', $promotion_pricing->id) : route('admin.promotion-pricings.store') }}" method="POST" enctype="multipart/form-data"> 
                            @csrf
                            @isset($promotion_pricing)
                                @method('put')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="country-select" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Choose Country 
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Select the country where the promotion pricing will apply. If no specific country is chosen, the default settings will be used."></i>
                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="country_id" id="country-select" class="form-select">
                                            <option value="" disabled selected>Select Country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}" {{ (old('country_id') ?? ($promotion_pricing->country_id ?? '')) == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                        
                                {{-- <div class="row col-xl-10 col-sm-12">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Currency 
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Select the currency to be used"></i>
                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="currency_id" id="country-select" class="form-select">
                                            <option value="" disabled selected>Select Currency</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}" {{ (old('country_id') ?? ($promotion_pricing->currency_id ?? '')) == $currency->id ? 'selected' : '' }}>
                                                    {{ $currency->short_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div> --}}
            
                                <div class="row col-xl-10 col-sm-12 mb-3 mt-3">
                                    <label for="input-placeholder2" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">
                                        Impressions 
                                        <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip" title="Enter the daily impressions. This is the number of impressions a post can reach in a day before charges are applied. It must be less than or equal to the system's already calculated daily impressions."></i>
                                        <small class="text-primary">(Current impressions:{{ number_format($post_performance->avg_impressions_per_day ?? 0) }})</small>
                                    </label>
                                    <!-- Place the paragraph text under the input field, outside of the label -->
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" class="form-control" name="impressions" id="input-placeholder2" value="{{ old('impressions', ($promotion_pricing->impressions ?? '')) }}" placeholder="Enter Number of impressions">
                                    </div>
                                    
                                </div>
                                
                                <div class="row col-xl-10 col-sm-12 mb-3 mt-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Amount
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Enter the daily amount. This is the payment to be made each day if a post reaches the specified daily impressions."></i>
                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" class="form-control" name="amount" id="input-placeholder" value="{{ old('amount', ($promotion_pricing->amount ?? '')) }}" placeholder="Enter New Amount">
                                    </div>
                                </div>
                                {{-- <div class="row col-xl-10 col-sm-12 mb-3 mt-3">
                                    <label for="input-placeholder1" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Max Daily Amount
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Enter the maximum daily amount for the promotion"></i>
                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" class="form-control" name="max_daily_amount" id="input-placeholder1" value="{{ old('max_daily_amount', ($promotion_pricing->max_daily_amount ?? '')) }}" placeholder="Enter Max Daily Amount">
                                    </div>
                                </div> --}}
                        
                                <div class="row col-xl-10 col-sm-12">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2" >Default 
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Choose if this pricing is the default option. It will be used as default for countries without specific settings."></i>

                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="default" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($boolOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('default') ?? ($promotion_pricing->default ?? '')) == $key ? 'selected' : '' }}>
                                                    {{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                        
                                <div class="row col-xl-10 col-sm-12 mt-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2" >Status 
                                        <i class="bi bi-info-circle text-primary " data-bs-toggle="tooltip" title="Select the status of the pricing. Options include 'Active' for current usage and 'Inactive' for pricing that is no longer in effect."></i>

                                    </label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('status') ?? ($promotion_pricing->status ?? '')) == $key ? 'selected' : '' }}>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        $(".typeSelect").on("change", function() {
            const value = $(this).val();
            console.log(value);
            updateType(value)
        });


        $(window).on("load", function() {
            const value = $(".typeSelect").find("option:selected").val();
            updateType(value);
        })
    </script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for the country select box
            $('#country-select').select2({
                placeholder: "Select or search for a country", // Set the placeholder text
                allowClear: true, // Allow clearing the selected country
                minimumResultsForSearch: 10, // Show search box only after 10 options are displayed
                ajax: {
                    url: "{{ route('admin.search-countries') }}", // Your AJAX URL to search countries
                    dataType: 'json',
                    data: function(params) {
                        return {
                            q: params.term // Send the search query to the server
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(function(country) {
                                return {
                                    id: country.id,
                                    text: country.name
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Display all countries initially in the dropdown (without search)
            $('#country-select').on('select2:open', function() {
                var dropdown = $(this).data('select2').dropdown.$dropdown;
                if (!dropdown.find('.select2-search--dropdown').length) {
                    dropdown.prepend(
                        '<input type="text" class="select2-search__field" autocomplete="off" />');
                }
            });
        });
    </script>
@endsection
