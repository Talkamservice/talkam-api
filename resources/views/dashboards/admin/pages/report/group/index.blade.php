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
                <div class="card-header">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="search">Search(status or reason)</label>
                            <input class="form-control" type="text" value="{{ request()->search }}" placeholder="Search..." name="search">
                        </div>
                    
                        <div class="form-group me-2">
                            <label for="date">Date</label>
                            <input class="form-control" type="date" value="{{ request()->date }}" name="date">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    
                </div>
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
                                            <a class="text-primary"
                                                href="{{ url('https://web.talkam.prodevs.io/group/' . $group_report->group->id . '/featured') }}"
                                                target="_blank"
                                                rel="noopener noreferrer">{{ $group_report->group->name }}</a>
                                        </td>
                                        <td>{{ $group_report->group->description }}</td>
                                        <td>
                                            <span
                                                class="badge bg-{{ pillClasses($group_report->group->status) }}-transparent">
                                                {{ $group_report->group->status }}
                                            </span>
                                        </td>
                                        <td>{{ $group_report->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.group.show', $group_report->id) }}">
                                                            <i class="ri-eye-line"></i> View
                                                        </a>
                                                    </li>
                                                    @if ($group_report->group->status === 'Suspended')
                                                        <!-- Undo Suspension Form -->
                                                        <form id="undoSuspensionForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.activate', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to lift this suspension?')">
                                                            @csrf
                                                            <a class="dropdown-item text-primary" href="#"
                                                                onclick="document.getElementById('undoSuspensionForm_{{ $group_report->id }}').submit()">
                                                                <i class="ri-alert-line"></i> Undo Suspension
                                                            </a>
                                                        </form>

                                                        <!-- Delete Group Form -->
                                                        <form id="deleteGroupForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.delete', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to permanently delete this group?')">
                                                            @csrf
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="document.getElementById('deleteGroupForm_{{ $group_report->id }}').submit()">
                                                                <i class="ri-alert-line"></i> Delete
                                                            </a>
                                                        </form>

                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openModal('{{ $group_report->group->id }}')">
                                                                <i class="ri-alert-line"></i> Suspend/Ban
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openModal('{{ $group_report->group->id }}')">
                                                                <i class="ri-alert-line"></i> Suspend/Ban
                                                            </a>
                                                        </li>
                                                        <!-- Delete Group Form -->
                                                        <form id="deleteGroupForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.delete', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to permanently delete this group?')">
                                                            @csrf
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="document.getElementById('deleteGroupForm_{{ $group_report->id }}').submit()">
                                                                <i class="ri-alert-line"></i> Delete
                                                            </a>
                                                        </form>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center"><img class="no-data-image"
                                                src="{{ asset('admin_assets/images/empty/no-data-concept-illustration.jpg') }}"
                                                alt=""></td>
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

  @include('dashboards.admin.pages.report.group.suspend-ban-modal')
       
@endsection
