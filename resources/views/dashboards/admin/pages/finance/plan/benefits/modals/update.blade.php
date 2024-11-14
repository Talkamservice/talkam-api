<div class="modal fade" id="updateBenefit_{{ $plan_benefit->id }}" tabindex="-1" role="dialog" aria-labelledby="updateBenefit_{{ $plan_benefit->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.plans.plan-benefits.update', [$plan->id, $plan_benefit->id]) }}" method="POST"> @csrf @method("patch")
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Update Benefit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <input type="hidden" name="plan_benefit_id" value="{{ $plan_benefit->id }}">
                <div class="modal-body">
                    <textarea class="form-control" required name="title" placeholder="Enter benefit..." rows="4">{{ old('title') ?? ($plan_benefit->title ?? '') }}</textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn" type="button" data-bs-dismiss="modal"><i class="flaticon-cancel-12"></i> Discard</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
