<?php

namespace App\Http\Controllers\Admin\Finance\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\PlanException;
use App\Exceptions\Payment\PlanException as PaymentPlanException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Finance\Plan\PlanBenefitService;
use App\Services\Finance\Plan\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlanController extends Controller
{
    protected $plan_service;

    public function __construct()
    {
        $this->plan_service = new PlanService;
    }

    public function index()
    {
        $plans = Plan::with(["durations"])->get();
        return view('dashboards.admin.pages.finance.plan.index', [
            'plans' => $plans,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "users" => User::where('status', StatusConstants::ACTIVE)->get()
        ]);
    }

    public function create()
    {
        return view('dashboards.admin.pages.finance.plan.create', [
            "benefits" => collect([]),
            "featuresOptions" => PlanConstants::PLAN_FEATURES,
            "frequencyOptions" => PlanConstants::FREQUENCY_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "featureCards" => PlanConstants::FEATURE_CARDS
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->plan_service->save($request->all());
            return redirect()->route("admin.plans.index")->with(NotificationConstants::SUCCESS_MSG, 'Plan created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function show($id)
    {
        $plan = PlanService::getById($id);
        return redirect()->route("admin.plans.plan-benefits.index", $plan->id);
    }

    public function edit($id)
    {
        $plan = PlanService::getById($id);
        $benefits = PlanBenefitService::getByPlanId($plan->id);
        return view('dashboards.admin.pages.finance.plan.create', [
            "plan" => $plan,
            "benefits" => $benefits,
            "frequencyOptions" => PlanConstants::FREQUENCY_OPTIONS,
            "featuresOptions" => PlanConstants::PLAN_FEATURES,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "featureCards" => PlanConstants::FEATURE_CARDS
        ]);
    }


    public function update(Request $request, $id)
    {
        try {
            $this->plan_service->save($request->all(), $id);
            return redirect()->route("admin.plans.index")
                ->with(NotificationConstants::SUCCESS_MSG, 'Plan updated successfully');
        } catch (ValidationException $e) {
            throw $e;
        } catch (PaymentPlanException $e) {
            return back()->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    public function destroy($id)
    {
        try {
            $plan = PlanService::getById($id);
            $this->plan_service->cancelFlutterwavePlan($plan);
            $plan->delete();
            return back()->with(NotificationConstants::SUCCESS_MSG, 'Plan deleted successfully');
        } catch (Throwable $th) {
            return back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    // public function cancelPlan($id)
    // {
    //     // dd($id);
    //     try {
    //         $this->plan_service->cancel($id);
    //         return redirect()->route("admin.plans.index")
    //             ->with(NotificationConstants::SUCCESS_MSG, 'Plan cancelled successfully');
    //     } catch (ValidationException $e) {
    //         throw $e;
    //     } catch (PaymentPlanException $e) {
    //         return back()->with(NotificationConstants::ERROR_MSG, $e->getMessage());
    //     } catch (Throwable $e) {
    //         throw $e;
    //         return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
    //     }
    // }
}
