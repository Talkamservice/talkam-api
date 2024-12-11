<?php

namespace App\Http\Controllers\Admin\Finance\Promotion;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\PromotionPricing;
use App\Services\Promotion\PromotionPricingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromotionPricingSettingController extends Controller
{
    protected $promotion_pricing_service;

    public function __construct()
    {
        $this->promotion_pricing_service = new PromotionPricingService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search =  $request->get('search');
        $promotion_pricings = PromotionPricing::with(['country', 'currency'])->search($search)->latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.finance.promotions.pricing-setting.index', [
            'promotion_pricings' => $promotion_pricings,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "sn" => $promotion_pricings->firstItem(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.finance.promotions.pricing-setting.create', [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            'countries' => Country::all(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->promotion_pricing_service->save($request->all());
            return redirect()->route("admin.promotion-pricings.index")->with(NotificationConstants::SUCCESS_MSG, 'country plan pricing created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $country_plan_pricing = $this->promotion_pricing_service->getById($id);
        return view('dashboards.admin.pages.finance.promotions.pricing-setting.create', [
            'promotion-pricing' => $country_plan_pricing,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            'countries' => Country::all(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->promotion_pricing_service->save($request->all(), $id);
            return redirect()->route("admin.promotion-pricings.index")
                ->with(NotificationConstants::SUCCESS_MSG, 'Promotion pricing updated successfully');
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $country_plan_pricing = $this->promotion_pricing_service->getById($id);
            $country_plan_pricing->delete();
            return back()->with(NotificationConstants::SUCCESS_MSG, 'Promotion pricing deleted successfully');
        } catch (Throwable $th) {
            return back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }
}
