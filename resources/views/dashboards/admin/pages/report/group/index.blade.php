@extends('dashboards.admin.layout.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Reported Groups</h1>
        <div class="ms-md-1 ms-0">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item active" aria-current="page">Index</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header Close -->

    <!-- Reported Groups Section -->
    <div class="col-xl-12 mb-4">
        <div class="card custom-card">
            <div class="card-body">
                <div class="table-responsive" style="min-height: 250px">
                    <table class="table text-nowrap table-hover border table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">Group Name</th>
                                <th scope="col">Group Description</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group_report_lists as $group_id => $group_report)
                                <tr>
                                    <td>
                                        <a class="text-primary" href="{{ url('https://web.talkam.prodevs.io/group/' . $group_report->group->id . '/featured') }}" target="_blank" rel="noopener noreferrer">{{ $group_report->group->name }}</a>
                                    </td>
                                    <td>{{ $group_report->group->description }}</td>
                                    <td>
                                        <span class="badge bg-{{ pillClasses($group_report->group->status) }}-transparent">
                                            {{ $group_report->group->status }}
                                        </span>
                                    </td>
                                    <td>{{ $group_report->created_at->format('Y-m-d h:i A') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Action
                                            </a>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.reports.group.show', $group_report->id) }}">
                                                        <i class="ri-eye-line"></i> View
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="openModal('{{ $group_report->group->id }}')">
                                                        <i class="ri-alert-line"></i> Suspend/Ban
                                                    </a>
                                                </li>
                                                <li>
                                                    <form id="undoSuspension_{{$group_report->id }}"
                                                        action="{{ route('admin.reports.group.delete', $group_report->id) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Are you sure you want to permanently delete this group?')">
                                                        @csrf
                                                        <a class="dropdown-item text-danger" href="#"
                                                            onclick="document.getElementById('undoSuspension_{{ $group_report->id }}').submit()">
                                                            <i class="ri-alert-line"></i> Delete
                                                        </a>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form id="undoSuspension_{{$group_report->id }}"
                                                        action="{{ route('admin.reports.group.activate', $group_report->id) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Are you sure you want to lift this suspension?')">
                                                        @csrf
                                                        <a class="dropdown-item text-primary" href="#"
                                                            onclick="document.getElementById('undoSuspension_{{ $group_report->id }}').submit()">
                                                            <i class="ri-alert-line"></i> Undo Suspension
                                                        </a>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center"><img class="no-data-image" src="{{asset('admin_assets/images/empty/no-data-concept-illustration.jpg')}}" alt=""></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- <div class="card-footer">
                <div class="d-flex align-items-center">
                    <div>
                        {{ $group_report_lists->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div> --}}
        </div>
    </div>
</div>

<!-- Modal for Group Suspension/Ban -->
<div class="modal fade" id="suspendOrBanModal" tabindex="-1" aria-labelledby="suspendOrBanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="suspendOrBanModalLabel">Suspend or Ban Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="suspendOrBanForm" method="POST" action="">
                @csrf
                @method('post')
                <input type="hidden" name="action_type" id="actionType">
                <input type="hidden" name="group_id" id="groupId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="suspensionReason" class="form-label">Reason for Suspension/Ban</label>
                        <textarea id="suspensionReason" name="suspension_reason" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3" id="durationField" style="display">
                        <label for="suspensionDuration" class="form-label">Suspension Duration</label>
                        <select id="suspensionDuration" name="duration" class="form-select">
                            <option value="1">24-48 hours</option>
                            <option value="2">7 days</option>
                            <option value="3">30 days</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger" onclick="setActionType('ban')">Ban</button>
                    <button type="submit" class="btn btn-warning" onclick="setActionType('suspend')">Suspend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setActionType(type) {
        document.getElementById('actionType').value = type;

        const durationField = document.getElementById('durationField');
        durationField.style.display = type === 'suspend' ? 'block' : 'none';
    }

    function openModal(groupId) {
        document.getElementById('groupId').value = groupId;
        document.getElementById('suspendOrBanForm').action = `{{ route('admin.reports.group.suspend-or-ban', $group_report->group->id) }}`;
        new bootstrap.Modal(document.getElementById('suspendOrBanModal')).show();
    }
</script>

@endsection
