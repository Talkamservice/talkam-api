<!-- Modal for Confirmation -->
<div class="modal fade" id="cancelPlanModal{{ $plan->id }}" tabindex="-1" aria-labelledby="cancelPlanModalLabel{{ $plan->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelPlanModalLabel">Cancel Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{route('admin.plan.cancel', $plan->id)}}" method="POST"> <!-- No static action here -->
                @csrf
                @method('PUT') <!-- This tells Laravel to treat it as a PUT request -->
                <div class="modal-body">
                    Are you sure you want to cancel this plan?
                    <input type="hidden" name="plan_id" id="plan_id" value=""> <!-- Hidden field for plan ID -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger" id="confirmCancelButton">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- <script>
    // Event listener for opening the modal and setting the plan ID
    const cancelButtons = document.querySelectorAll('[data-bs-target="#cancelPlanModal"]');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const planId = this.getAttribute('data-plan-id');
            document.getElementById('plan_id').value = planId; // Set the plan ID in the hidden input

            // Update the form action dynamically with the correct plan ID
            const form = document.getElementById('cancelPlanForm');
            form.action = `/admin/plan/cancel/${planId}`; // Set the form action with the correct plan ID
        });
    });

    // Event listener for the confirm cancel button
    document.getElementById('confirmCancelButton').addEventListener('click', function() {
        document.getElementById('cancelPlanForm').submit(); // Submit the form
    });
</script> --}}
