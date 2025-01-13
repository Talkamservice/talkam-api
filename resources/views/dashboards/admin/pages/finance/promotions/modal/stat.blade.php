<div class="modal fade" id="{{ $modalKey }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalKey }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Promotion Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>    
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-check-circle"></i> <strong>Status:</strong> {{ $promotion->status ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-comment"></i> <strong>Comments:</strong> {{ $promotion_stats["comments"] ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-like"></i> <strong>Likes:</strong> {{ $promotion_stats["likes"] ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-dislike"></i> <strong>Dislikes:</strong> {{ $promotion_stats["dislikes"] ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-share-alt"></i> <strong>Shares:</strong> {{ $promotion->statAttribute('shares') ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-bar-chart"></i> <strong>Impressions:</strong> {{ $promotion->statAttribute('impressions') ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-group"></i> <strong>Engagements:</strong> {{ $promotion_stats["engagements"] ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-group"></i> <strong>Engagements Rate:</strong> {{ ($promotion_stats["engagement_rates"] . "%") ?? 'No data' }}
                        </div>
                    </div>
                    {{-- <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-user"></i> <strong>Followers:</strong> {{ $promotion->statAttribute('followers') ?? 'No data' }}
                        </div>
                    </div> --}}
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-user-circle"></i> <strong>Profile Visits:</strong> {{ $promotion->statAttribute('profile_visits') ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-mouse-alt"></i> <strong>Clicks:</strong> {{ $promotion->statAttribute('clicks') ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-time"></i> <strong>Min Time Spent (s):</strong> {{ $promotion->statAttribute('min_time_spent') ?? 'No data' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group-item">
                            <i class="bx bx-timer"></i> <strong>Max Time Spent (s):</strong> {{ $promotion->statAttribute('max_time_spent') ?? 'No data' }}
                        </div>
                    </div>
                </div>  
            </div>
        </div>
    </div>
</div>
