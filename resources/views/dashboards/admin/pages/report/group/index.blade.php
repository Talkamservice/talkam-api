@extends('dashboards.admin.layout.app')

@section('content')
<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Group Report</h1>
        <div class="ms-md-1 ms-0">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="#">Group Report</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Index</li>
                </ol>
            </nav>
            <div class=""></div>
        </div>
    </div>
    <!-- Page Header Close -->

    <!-- Start::row-1 -->
    <div class="col-xl-12">
        <div class="card custom-card">

            <div class="card-body">
                <div class="table-responsive" style="min-height: 250px">
                    <table class="table text-nowrap table-hover border table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">Group Name</th>
                                <th scope="col">Description</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group_report_lists->groupBy('group_id') as $group_id => $group_reports)
                                @php
                                    $first_report = $group_reports->first();
                                @endphp
                                @if ($first_report && $first_report->group && $first_report->user)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.users.show', $first_report->id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $first_report->image }}" alt="img">
                                                    </span><a href="{{ url('https://web.talkam.prodevs.io/group/' . $first_report->group->id . '/featured') }}" target="_blank" rel="noopener noreferrer">{{ $first_report->group->name }}</a>

                                                </div>
                                            </a>
                                        </td>
                                        <td>{{ $first_report->group->description }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($first_report->status) }}-transparent">
                                                {{ $first_report->status }}
                                            </span>
                                        </td>
                                        <td>{{ $first_report->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.group.show', $first_report->id) }}">
                                                            <i class="ri-eye-line"></i> | View
                                                        </a>
                                                    </li>
                                                  
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="5" class="text-center">A/Some data is/are missing or removed</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No record found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex align-items-center">
                    <div>
                        {{ $group_report_lists->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
