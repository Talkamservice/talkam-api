@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-title fw-semibold fs-18 mb-0">Announcements</h1>
            </div>
            <div class="ms-md-1 ms-0 d-flex align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Announcements</a></li>
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
                    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
                        <i class="fe fe-plus"></i> <span class="ml-3">Create</span>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        @if ($announcements->isNotEmpty())
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">Banner</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Message</th>
                                        <th scope="col">Audience</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Published At</th>
                                        <th scope="col">Expired At</th>
                                        <th scope="col">Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($announcements as $announcement)
                                        <tr>
                                            <td class="d-flex justify-content-center">
                                                <span>
                                                    <img src="{{ $announcement->banner_image }}" alt=""
                                                        style="width: 50px; height:50px: border-radius:10px">
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    title="{{ $announcement->title }}">{{ Str::limit($announcement->title, 30) }}</span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#announcementContent_{{ $announcement->id }}">
                                                    View
                                                </button>
                                            </td>
                                            <td> {{ ucwords(str_replace('_', ' ', strtolower($announcement->audience))) }}
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ pillClasses($announcement->status) }}-transparent">
                                                    {{ $announcement->status }}
                                                </span>
                                            </td>
                                            <td>{{ $announcement->published_at ?? 'N/A' }}</td>
                                            @php
                                                $isExpired = \Carbon\Carbon::now()->greaterThan(
                                                    $announcement->expired_at ?? now(),
                                                );
                                            @endphp

                                            <td class="{{ $isExpired ? 'text-danger' : '' }}">
                                                @if ($announcement->expired_at)
                                                    {{ \Carbon\Carbon::parse($announcement->expired_at)->format('d/m/Y H:i') }}
                                                    @if ($isExpired)
                                                        <sub class="text-danger">(Expired)</sub>
                                                    @endif
                                                @else
                                                    N/A
                                                @endif
                                            </td>

                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        @php
                                                            $statuses = [
                                                                'Active' => [
                                                                    'icon' => 'ri-check-line',
                                                                    'class' => 'text-success',
                                                                    'label' => 'Mark As Active',
                                                                    'tooltip' => 'Activate the announcement',
                                                                ],
                                                                'Inactive' => [
                                                                    'icon' => 'ri-close-line',
                                                                    'class' => 'text-danger',
                                                                    'label' => 'Mark As Inactive',
                                                                    'tooltip' => 'Deactivate the announcement',
                                                                ],
                                                            ];
                                                            $isActive = $announcement->status === 'Active';
                                                        @endphp
                                            
                                                        <li>
                                                            @if (!$isActive)
                                                                <a class="dropdown-item"
                                                                   href="{{ route('admin.announcements.edit', $announcement->id) }}">
                                                                    <i class="ri-edit-2-line"></i> | Edit
                                                                </a>
                                                            @endif
                                                        </li>
                                            
                                                        @foreach ($statuses as $status => $details)
                                                            @if (($isActive && $status === 'Active') || (!$isActive && $status === 'Inactive'))
                                                                <!-- Do not show the form for the current status -->
                                                                @continue
                                                            @endif
                                                            <li>
                                                                <a class="dropdown-item {{ $details['class'] }}"
                                                                   href="#"
                                                                   onclick="openActionModal('{{ strtolower($status) }}-announcement', '{{ route('admin.announcements.update-status', $announcement->id) }}', '{{ $status }}', '{{ $details['label'] }}', '{{ $details['icon'] }}')"
                                                                   data-bs-toggle="tooltip"
                                                                   title="{{ $details['tooltip'] }}">
                                                                    <i class="{{ $details['icon'] }}"></i> | {{ $details['label'] }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                            
                                                        <li>
                                                            <a class="dropdown-item text-danger"
                                                               href="#"
                                                               onclick="openActionModal('delete-announcement', '{{ route('admin.announcements.destroy', $announcement->id) }}')"
                                                               data-bs-toggle="tooltip"
                                                               title="Delete the announcement">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                            

                                        </tr>
                                        <!-- Hidden Delete Form -->
                                        <form id="deleteannouncementForm_{{ $announcement->id }}"
                                            action="{{ route('admin.announcements.destroy', $announcement->id) }}"
                                            method="POST" style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        @include('dashboards.admin.pages.bulk-messages.modal.notification_content_modal',[
                                                'modalKey' => "announcementContent_$announcement->id",
                                                'modalContent' => $announcement->body,
                                            ]
                                        )
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
                            {{ $announcements->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.announcement.actions-modal')
 
@endsection
