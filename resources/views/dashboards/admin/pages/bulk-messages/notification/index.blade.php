@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-title fw-semibold fs-18 mb-0">Notifications</h1>
            </div>
            <div class="ms-md-1 ms-0 d-flex align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Notifications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>

            </div>
        </div>

        <!-- Page Header Close -->
        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-end">
                    <a href="{{ route('admin.notifications.send-bulk-notification.create') }}" class="btn btn-primary btn-sm"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        @if ($notifications->isNotEmpty())
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">S/N</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Message</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Scheduled Date</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($notifications as $notification)
                                        <tr>
                                            <td>
                                                {{ $sn++ }}
                                            </td>
                                            <td>
                                                <span title="{{ $notification->title }}">{{ Str::limit($notification->title, 50) }}</span>
                                            </td>
                                            <td>
                                                <button type="button" data-bs-toggle="modal" data-bs-target="#notificationContent_{{ $notification->id }}" class="btn btn-primary btn-sm show-body">
                                                    View
                                                </button>
                                            </td>

                                            <td>{{ $notification->type }}</td>
                                            <td>
                                                <span class="badge bg-{{ pillClasses($notification->status) }}-transparent">
                                                    {{ $notification->status }}
                                                </span>
                                            </td>
                                            <td>{{ $notification->schedule_date }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </a>
                                                    <ul class="dropdown-menu">

                                                        @if ($notification->status !== $Sent)
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('admin.notifications.send-bulk-notification.edit', $notification->id) }}">
                                                                    <i class="ri-edit-2-line"></i>| Edit
                                                                </a>
                                                            </li>
                                                        @endif

                                                        <li>
                                                            <a data-bs-toggle="tooltip" title="Delete Notification" class="dropdown-item text-danger" href="#"
                                                            onclick="openDeleteModal('{{ route('admin.notifications.send-bulk-notification.destroy', $notification->id) }}')">
                                                             <i class="ri-delete-bin-line"></i> | Delete
                                                         </a>
                                                         
                                                           
                                                        </li>

                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Hidden Delete Form -->
                                        @include('dashboards.admin.pages.bulk-messages.modal.notification_content_modal', [
                                            'modalKey' => "notificationContent_$notification->id",
                                            "modalContent" => $notification->message
                                        ])
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            @include('general.components.no_content')
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $notifications->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.delete-modal')
@endsection
