<?php

namespace App\Http\Controllers\Admin\Finance\Plan;

use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanBenefit;
use App\Models\PlanDuration;
use App\Services\Finance\Plan\PlanBenefitService;
use App\Services\Finance\Plan\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlanBenefitsController extends Controller
{
    protected $plan_benefit_service;

    public function __construct()
    {
        $this->plan_benefit_service = new PlanBenefitService;
    }

    public function index($plan_id)
    {
        $plan = PlanService::getById($plan_id);
        $plan_benefits = PlanBenefit::where('plan_id', $plan->id)->get();
        $plans = Plan::with('durations')->get();

        return view('dashboards.admin.pages.finance.plan.benefits.index', [
            'plan' => $plan,
            'plan_benefits' => $plan_benefits,
            'plans' => $plans,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    public function store(Request $request, Plan $plan)
    {
        try {
            $data = $this->plan_benefit_service->save($request->all());
            return redirect()->route("admin.plans.plan-benefits.index", $plan->id)->with(NotificationConstants::SUCCESS_MSG, $data["message"]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }


    public function edit($plan_id, $plan_benefit_id)
    {
        $plan = PlanService::getById($plan_id);
        $plan_benefit = PlanBenefitService::getById($plan_benefit_id);
        return view('dashboards.admin.pages.finance.plan.benefits.create', [
            'plan' => $plan,
            'plan_benefit' => $plan_benefit,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }
    
    public function update(Request $request, Plan $plan, $id)
    {
        try {
            $this->plan_benefit_service->save($request->all(), $id);
            return redirect()->route("admin.plans.plan-benefits.index", $plan->id)
                ->with(NotificationConstants::SUCCESS_MSG, 'Benefit updated successfully');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            // throw $e;
            return back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request");
        }
    }

    public function destroy(Plan $plan, $id)
    {
        $plan_benefit = PlanBenefitService::getById($id);
        $plan_benefit->delete();
        return redirect()->route("admin.plans.plan-benefits.index", $plan->id)
            ->with(NotificationConstants::SUCCESS_MSG, 'Benefit deleted successfully');
    }
}
