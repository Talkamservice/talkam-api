@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Application Details</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.therapist-applications.index') }}">Applications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $application->user->full_name }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="row">
            <!-- Applicant Info -->
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Applicant Information</div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <span class="avatar avatar-xxl avatar-rounded">
                                <img src="{{ $application->user->avatarUrl() }}" alt="img">
                            </span>
                            <h5 class="mt-3 mb-1">{{ $application->user->full_name }}</h5>
                            <p class="text-muted mb-1">{{ $application->user->email }}</p>
                            <p class="text-muted mb-3">{{ $application->user->phone_number }}</p>
                            
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
                            <span class="badge bg-{{ $color }} fs-14">
                                {{ ucwords(str_replace('_', ' ', $application->status)) }}
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold">User ID:</td>
                                        <td>{{ $application->user_id }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">Application ID:</td>
                                        <td>{{ $application->id }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">Submitted:</td>
                                        <td>
                                            @if($application->submitted_at)
                                                {{ $application->submitted_at->format('M d, Y') }}
                                            @else
                                                <span class="text-muted">Not submitted</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">Reviewed:</td>
                                        <td>
                                            @if($application->reviewed_at)
                                                {{ $application->reviewed_at->format('M d, Y') }}
                                            @else
                                                <span class="text-muted">Not reviewed</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if(in_array($application->status, ['submitted', 'in_review']))
                            <div class="mt-4 d-grid gap-2">
                                <button type="button" class="btn btn-success" onclick="approveApplication({{ $application->id }})">
                                    <i class="ti ti-check"></i> Approve Application
                                </button>
                                <button type="button" class="btn btn-danger" onclick="showRejectModal({{ $application->id }})">
                                    <i class="ti ti-x"></i> Reject Application
                                </button>
                            </div>
                        @endif

                        @if($application->status === 'rejected' && $application->rejection_reason)
                            <div class="alert alert-danger mt-3" role="alert">
                                <strong>Rejection Reason:</strong><br>
                                {{ $application->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Professional Details -->
            <div class="col-xl-8">
                <!-- Credentials -->
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Professional Credentials</div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Credential Type</label>
                                <p class="fw-semibold">{{ $application->credential_type ?? 'Not provided' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Years of Experience</label>
                                <p class="fw-semibold">{{ $application->years_experience ?? 'Not provided' }} years</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Session Rate</label>
                                <p class="fw-semibold">₦{{ number_format($application->session_rate ?? 0) }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Session Duration</label>
                                <p class="fw-semibold">{{ $application->session_duration ?? 'Not provided' }} minutes</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Buffer Time</label>
                                <p class="fw-semibold">{{ $application->buffer_minutes ?? 'Not provided' }} minutes</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Session Formats</label>
                                <p class="fw-semibold">
                                    @if($application->session_formats && is_array($application->session_formats))
                                        @foreach($application->session_formats as $format)
                                            <span class="badge bg-primary-transparent me-1">{{ ucfirst($format) }}</span>
                                        @endforeach
                                    @else
                                        Not provided
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Biography -->
                @if($application->bio)
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Professional Bio</div>
                        </div>
                        <div class="card-body">
                            <p>{{ $application->bio }}</p>
                        </div>
                    </div>
                @endif

                <!-- Specialties -->
                @if($application->specialties && $application->specialties->count() > 0)
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Specialties</div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($application->specialties as $specialty)
                                    <span class="badge bg-info-transparent fs-14">
                                        {{ $specialty->category->name ?? 'Unknown' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Documents -->
                @if($application->documents && $application->documents->count() > 0)
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">Uploaded Documents</div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Expires</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($application->documents as $document)
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary-transparent">
                                                        {{ ucfirst($document->type) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @php
                                                        $docStatusColors = [
                                                            'pending' => 'warning',
                                                            'approved' => 'success',
                                                            'rejected' => 'danger'
                                                        ];
                                                        $docColor = $docStatusColors[$document->status] ?? 'secondary';
                                                    @endphp
                                                    <span class="badge bg-{{ $docColor }}-transparent">
                                                        {{ ucfirst($document->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($document->expires_at)
                                                        {{ \Carbon\Carbon::parse($document->expires_at)->format('M d, Y') }}
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($document->file_url)
                                                        <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-primary">
                                                            <i class="ti ti-download"></i> View
                                                        </a>
                                                    @endif
                                                    @if($document->status === 'pending')
                                                        <button class="btn btn-sm btn-success" onclick="approveDocument({{ $document->id }})">
                                                            <i class="ti ti-check"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectDocument({{ $document->id }})">
                                                            <i class="ti ti-x"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Reject Application Modal -->
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
                                      placeholder="Please provide a detailed reason..." 
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
                        window.location.href = '{{ route("admin.therapist-applications.index") }}';
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

        function approveDocument(documentId) {
            if (confirm('Approve this document?')) {
                fetch(`/admin/therapist-applications/documents/${documentId}/verdict`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: 'approved' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Document approved!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to approve document'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
            }
        }

        function rejectDocument(documentId) {
            const reason = prompt('Reason for rejecting this document:');
            if (reason) {
                fetch(`/admin/therapist-applications/documents/${documentId}/verdict`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        status: 'rejected',
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Document rejected!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to reject document'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
            }
        }
    </script>
@endsection
