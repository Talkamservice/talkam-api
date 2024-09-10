@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Start::page-header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Welcome back, {{ auth()->user()?->name }}</p>
            </div>
            {{-- <div class="btn-list mt-md-0 mt-2">
                <button type="button" class="btn btn-primary btn-wave">
                    <i class="ri-filter-3-fill me-2 align-middle d-inline-block"></i>Filters
                </button>
                <button type="button" class="btn btn-outline-secondary btn-wave">
                    <i class="ri-upload-cloud-line me-2 align-middle d-inline-block"></i>Export
                </button>
            </div> --}}
        </div>

        <!-- End::page-header -->


        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-12 col-xl-12">
                <div class="row">
                    @foreach ($cards as $card)
                        <div class="col-12 col-md-6 col-lg-6 col-xl-6 col-xxl-3">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-top justify-content-between">
                                        <div>
                                            <span class="avatar avatar-md avatar-rounded bg-{{ $card['class'] }}">
                                                <i class="ti ti-{{ $card['icon'] ?? 'udrtd' }} fs-16"></i>
                                            </span>
                                        </div>
                                        <div class="flex-fill ms-3">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                                <div>
                                                    <p class="text-muted mb-0">{{ $card['title'] }}</p>
                                                    <h4 class="fw-semibold mt-1">{{ $card['value'] }}</h4>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-1">
                                                <div>
                                                    <a class="text-{{ $card['class'] }}" href="{{ $card['url'] }}">
                                                        View All
                                                        <i class="ti ti-arrow-narrow-right ms-2 fw-semibold d-inline-block"></i>
                                                    </a>
                                                </div>
                                                <div class="text-end">
                                                    <p class="mb-0 text-{{ $card['percentage'] >= 0 ? 'success' : 'danger' }} fw-semibold">
                                                        {{ $card['percentage'] >= 0 ? '+' : '' }}{{ $card['percentage'] }}%
                                                    </p>
                                                    <span class="text-muted op-7 fs-11">this month</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="row">
                    <div class="col-12 col-md-7 col-lg-7 col-xl-7 col-xxl-7">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Latest Users
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table text-nowrap table-hover border table-bordered">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Email</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($users as $user)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center fw-semibold">
                                                            <span class="avatar avatar-sm me-2 avatar-rounded">
                                                                <img src="{{ $user->avatarUrl() }}" alt="img">
                                                            </span>{{ $user->name }}
                                                        </div>
                                                    </td>
                                                    <td>{{ $user->email }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ pillClasses($user->status) }}-transparent">
                                                            {{ $user->status }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $user->created_at->format('Y-m-d h:i A') }}</td>
                                                    <td>
                                                        <div class="hstack gap-2 fs-15">
                                                            <a aria-label="anchor"
                                                                href="{{ route('admin.users.show', $user->id) }}"
                                                                class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light">
                                                                <i class="ri-eye-line"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                @include('general.components.no_item', ['items' => []])
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                
                                <!-- Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} entries
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            {{ $users->links('pagination::bootstrap-4') }}
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-5 col-lg-5 col-xl-5 col-xxl-5">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Recent Activity
                                </div>
                                <div class="dropdown">
                                    <a href="{{route('admin.activity-logs.index')}}" class="p-2 fs-12 text-muted"
                                        aria-expanded="false">
                                       @if ($activity_logs->count() >= 5)
                                       View All
                                       @endif
                                    </a>

                                </div>
                            </div>
                            <div class="card-body">
                                <div>
                                    <ul class="list-unstyled mb-0 crm-recent-activity">
                                        @forelse ($activity_logs as $log)
                                            <li class="crm-recent-activity-content">
                                                <div class="d-flex align-items-top">
                                                    <div class="me-3">
                                                        <span
                                                            class="avatar avatar-xs bg-primary-transparent avatar-rounded">
                                                            <i class="bi bi-circle-fill fs-8"></i>
                                                        </span>
                                                    </div>
                                                    <div class="crm-timeline-content">
                                                        <span class="fw-semibold">
                                                            {{ ucfirst($log->description) }}
                                                        </span>
                                                    </div>
                                                    <div class="flex-fill text-end">
                                                        <span
                                                            class="d-block text-muted fs-11 op-7">{{ $log->created_at->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        @empty
                                            @include('general.components.no_item', ['items' => []])
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection