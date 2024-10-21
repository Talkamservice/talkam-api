<!-- Subscription Modal -->
<div class="modal fade" id="subscriptionModal{{$plan->id}}" tabindex="-1" aria-labelledby="subscriptionModalLabel{{$plan->id}}" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- Keep 'modal-lg' for larger modal -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscriptionModalLabel">Subscribe TO Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{route('admin.subscriptions.flutterwave.subscribe', $plan->id)}}" method="POST"> <!-- Ensure to set the correct action -->
                    @csrf
                    <input type="hidden" name="plan_duration_id" value="{{ $plan->durations->first()->id }}">

                    {{-- <div class="row col-xl-12 col-sm-12 mb-3" id="user-container" >
                        <label for="users" class="form-label">Select User</label>
                        <div id="selected-users" class="mb-2"></div> <!-- Container for displaying selected users -->
                        <select name="user_id" id="user" class="form-control">
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ $user->id, old('user_id') ? 'selected' : '' }}>
                                    {{ $user->getName() }}
                                </option>
                            @endforeach
                        </select>
                    </div> --}}
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2">Proceed</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
