<div class="modal fade" id="createBenefit" tabindex="-1" role="dialog" aria-labelledby="createBenefit" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.plans.plan-benefits.store', [$plan->id]) }}" method="POST"> @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Create Benefit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <div class="modal-body">
                    <textarea class="form-control" required name="title" placeholder="Enter benefit..." rows="4">{{ old('title') ?? null }}</textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn" type="button" data-bs-dismiss="modal"><i class="flaticon-cancel-12"></i> Discard</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>