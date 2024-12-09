<?php

namespace App\Http\Controllers\Admin\Finance\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PlanCountryPricing;
use App\Models\PlanCountryPricingProvider;
use App\Models\PlanDuration;
use App\Services\Finance\Plan\PlanCountryPricingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlanCountryPricingController extends Controller
{
    protected $country_plan_pricing_service;

    public function __construct()
    {
        $this->country_plan_pricing_service = new PlanCountryPricingService;
    }

    public function index(Request $request)
    {
        $search =  $request->get('search');
        $country_plan_pricings = PlanCountryPricing::with(['country', 'plan'])->search($search)->latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.finance.plan.country-pricing.index', [
            'country_plan_pricings' => $country_plan_pricings,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "sn" => $country_plan_pricings->firstItem(),
        ]);
    }

    public function create()
    {
        $plan_durations = PlanDuration::where("status", StatusConstants::ACTIVE)
            ->where("price", ">", 0)
            ->get();
        return view('dashboards.admin.pages.finance.plan.country-pricing.create', [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "typeOptions" => PlanConstants::COUNTRY_TYPE_OPTIONS,
            'plan_durations' => $plan_durations,
            'countries' => Country::all(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->country_plan_pricing_service->save($request->all());
            return redirect()->route("admin.country-plan-pricings.index")->with(NotificationConstants::SUCCESS_MSG, 'country plan pricing created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function show($id)
    {
        $plan = $this->country_plan_pricing_service->getById($id);

        $country_plan_pricing_providers = PlanCountryPricingProvider::where(["plan_country_pricing_id"=> $plan->id])->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.finance.plan.country-pricing.show", [
            'sn' => $country_plan_pricing_providers->firstItem(),
            'country_plan_pricing' => $plan,
            'country_plan_pricing_providers' => $country_plan_pricing_providers,
        ]);
    }

    public function edit($id)
    {
        $country_plan_pricing = $this->country_plan_pricing_service->getById($id);
        $plan_durations = PlanDuration::where("status", StatusConstants::ACTIVE)->where("price", ">", 0)->get();
        return view('dashboards.admin.pages.finance.plan.country-pricing.create', [
            'country_plan' => $country_plan_pricing,
            'plan_durations' => $plan_durations,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "typeOptions" => PlanConstants::COUNTRY_TYPE_OPTIONS,
            'countries' => Country::all(),
        ]);
    }


    public function update(Request $request, $id)
    {
        try {
            $this->country_plan_pricing_service->save($request->all(), $id);
            return redirect()->route("admin.country-plan-pricings.index")
                ->with(NotificationConstants::SUCCESS_MSG, 'Country plan pricing updated successfully');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            // Catch the exception thrown when a country plan already exists
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    public function destroy($id)
    {
        try {
            $country_plan_pricing = $this->country_plan_pricing_service->getById($id);
            $this->country_plan_pricing_service->deleteCountryPricing($country_plan_pricing);
            return back()->with(NotificationConstants::SUCCESS_MSG, 'Country pricing deleted successfully');
        } catch (Throwable $th) {
            return back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    public function deleteCountryPlanProvider($plan_pricing_id, $plan_pricing_provider_id)
    {
        try {
            $plan_pricing_provider = PlanCountryPricingProvider::find($plan_pricing_provider_id);
            $this->country_plan_pricing_service->deleteCountryPricingProvider($plan_pricing_provider);
            return back()->with(NotificationConstants::SUCCESS_MSG, 'Country pricing deleted successfully');
        } catch (Throwable $th) {
            return back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    public function fetchCountries(Request $request)
    {
        $search = $request->get('q'); // Search term from AJAX request
        $countries = Country::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%$search%");
            })
            ->limit(10) // Limit results for better performance
            ->get(['id', 'name']); // Return only id and name fields

        return response()->json($countries);
    }
}
