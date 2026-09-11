@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Therapist Applications</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Therapist Applications</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-top justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-primary">
                                    <i class="ti ti-file-description fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0">Total Applications</p>
                                        <h4 class="fw-semibold mt-1">{{ $statistics['total'] }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-top justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-secondary">
                                    <i class="ti ti-clock fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0">Pending Review</p>
                                        <h4 class="fw-semibold mt-1">{{ $statistics['submitted'] }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-top justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-success">
                                    <i class="ti ti-check fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0">Approved</p>
                                        <h4 class="fw-semibold mt-1">{{ $statistics['approved'] }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-top justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-danger">
                                    <i class="ti ti-x fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0">Rejected</p>
                                        <h4 class="fw-semibold mt-1">{{ $statistics['rejected'] }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header">
                    <form action="{{ route('admin.therapist-applications.index') }}" method="get" class="d-flex justify-content-between flex-wrap">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" value="{{ request()->search }}"
                                placeholder="Name or email..." name="search">
                        </div>
                        <div class="form-group me-2">
                            <label for="">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request()->status == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="submitted" {{ request()->status == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                <option value="in_review" {{ request()->status == 'in_review' ? 'selected' : '' }}>In Review</option>
                                <option value="approved" {{ request()->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request()->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group me-2">
                            <label for="">From</label>
                            <input class="form-control" type="date" value="{{ request()->from }}" name="from">
                        </div>
                        <div class="form-group me-2">
                            <label for="">To</label>
                            <input class="form-control" type="date" value="{{ request()->to }}" name="to">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">
                                <i class="ti ti-filter"></i> Filter
                            </button>
                            <a href="{{ route('admin.therapist-applications.index') }}" class="btn btn-sm btn-secondary p-2">
                                <i class="ti ti-refresh"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    @if($applications->count() > 0)
                        <div class="table-responsive">
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">S/N</th>
                                        <th scope="col">Applicant</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Credential Type</th>
                                        <th scope="col">Experience</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Submitted</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($applications as $application)
                                        <tr>
                                            <td>{{ $loop->iteration + ($applications->currentPage() - 1) * $applications->perPage() }}</td>
                                            <td>
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $application->user->avatarUrl() }}" alt="img">
                                                    </span>
                                                    {{ $application->user->full_name }}
                                                </div>
                                            </td>
                                            <td>{{ $application->user->email }}</td>
                                            <td>
                                                @if($application->credential_type)
                                                    <span class="badge bg-info-transparent">{{ $application->credential_type }}</span>
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($application->years_experience)
                                                    {{ $application->years_experience }} years
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'draft' => 'secondary',
                                                        'submitted' => 'warning',
                                                        'in_review' => 'info',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $color = $statusColors[$application->status] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}-transparent">
                                                    {{ ucwords(str_replace('_', ' ', $application->status)) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($application->submitted_at)
                                                    {{ $application->submitted_at->format('Y-m-d') }}<br>
                                                    <small class="text-muted">{{ $application->submitted_at->diffForHumans() }}</small>
                                                @else
                                                    <span class="text-muted">Not submitted</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.therapist-applications.show', $application->id) }}" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="ti ti-eye"></i> View
                                                    </a>
                                                    
                                                    @if(in_array($application->status, ['submitted', 'in_review']))
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="approveApplication({{ $application->id }})">
                                                            <i class="ti ti-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="showRejectModal({{ $application->id }})">
                                                            <i class="ti ti-x"></i> Reject
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <img src="{{ $admin_assets }}/images/media/no-data.svg" alt="No data" class="no-data-image mb-3">
                            <h5 class="text-muted">No applications found</h5>
                            <p class="text-muted">Applications will appear here when therapists register</p>
                        </div>
                    @endif
                </div>
                @if($applications->count() > 0)
                    <div class="card-footer">
                        <div class="d-flex align-items-center">
                            <div>
                                {{ $applications->links('pagination::bootstrap-4') }}
                            </div>
                            <div class="ms-auto">
                                <span class="text-muted">
                                    Showing {{ $applications->firstItem() }} to {{ $applications->lastItem() }} of {{ $applications->total() }} entries
                                </span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Reject Application</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" 
                                      placeholder="Please provide a detailed reason for rejecting this application..." 
                                      required></textarea>
                            <small class="text-muted">This will be sent to the applicant</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-x"></i> Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function approveApplication(applicationId) {
            if (confirm('Are you sure you want to approve this application? This will create a therapist account and grant them access to the platform.')) {
                fetch(`/admin/therapist-applications/${applicationId}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Application approved successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to approve application'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while approving the application');
                });
            }
        }

        function showRejectModal(applicationId) {
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            const form = document.getElementById('rejectForm');
            form.action = `/admin/therapist-applications/${applicationId}/reject`;
            modal.show();
        }
    </script>
@endsection
