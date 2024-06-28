@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Members</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Members</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xl-8 col-md-8 mb-3">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-end">
                        {{-- <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                            <div class="form-group me-2">
                                <label for="">Search</label>
                                <input class="form-control" type="text" placeholder="Search...." name="search">
                            </div>
                            <div class="form-group me-2" style="margin-top: 20px;">
                                <button class="btn btn-sm btn-success p-2">Filter</button>
                            </div>
                        </form> --}}
                        <div class="">
                            <a data-bs-toggle="modal" data-bs-target="#assignNewRoleModal" class="btn btn-sm btn-primary"><i class="fe fe-plus"></i> <span class="ml-3">Invite</span></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="min-height:300px">
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th class="col">Role</th>
                                        <th class="col">Joined</th>
                                        <th class="col">Status</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($admins as $admin)
                                        @php
                                            $user = $admin->user;
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="#" class="d-flex align-items-center">
                                                    <img src="{{ $admin->user->avatarUrl() }}" class="avatar rounded-circle me-3" alt="Avatar">
                                                    <div class="d-block">
                                                        <span class="fw-bold">{{ $user->name }}</span>
                                                        <div class="small text-gray">{{ $user->email }}</div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td><span class="fw-normal">{{ $admin->role }}</span></td>
                                            <td><span class="fw-normal">{{ $admin->created_at->format('Y-m-d h:i A') }}</span>
                                            </td>
                                            <td><span class="fw-normal text-{{ pillClasses($admin->status) }}">{{ $admin->status }}</span>
                                            </td>
                                            <td>
                                                <div class="btn-group d-flex justify-content-end">
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton2">
                                                            <li><a class="dropdown-item" href="{{ route('admin.users.show', $user->id) }}"><i class="ri-eye-line"></i> | View</a></li>
                                                            <li><a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#changeRoleModal_{{ $admin->id }}"><i class="ri-settings-2-line"></i> | Change Role</a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#removeAsAdminForm_{{ $admin->id }}"><i class="ri-delete-bin-line"></i> | Remove</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @include('dashboards.admin.pages.authorization.modals.invitation.delete_member')
                                        @include('dashboards.admin.pages.authorization.modals.role.change_role', [
                                            'form_url' => route('admin.members.change-role', [$admin->id]),
                                            'source' => 'admin',
                                            'roles' => $roles,
                                        ])
                                    @empty
                                        <div class="alert alert-info text-center">
                                            No record found
                                        </div>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex align-items-center">
                            <div>
                                {{ $admins->links('pagination::bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-12 col-md-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="mg-b-0">Pending Invitations</h6>
                    </div>
                    <ul class="list-group list-group-flush tx-13">
                        @forelse ($invitations as $invitation)
                            <li class="list-group-item d-flex align-items-center justify-content-between pd-sm-x-20">
                                <div class="d-flex">
                                    <div class="avatar"><span class="avatar-initial rounded-circle bg-gray-600">{{ $invitation->invitee_email[0] }}</span></div>
                                    <div class="pd-l-10" style="padding-left: 10px">
                                        <p class="" style="padding: 0px; margin: 0px">
                                            {{ $invitation->invitee_email }}
                                        </p>
                                        <small class="">
                                            Since: {{ $invitation->created_at }}
                                        </small>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="badge bg-{{ pillClasses($invitation->status) }}-transparent">
                                        {{ $invitation->status }}
                                    </div>
                                </div>
                                <div class="mg-l-auto d-flex align-self-center">
                                    <nav class="nav nav-icon-only">
                                        <a href="#" title="Delete Invitation" class="text-danger" data-bs-toggle="modal" data-bs-target="#delete_invite_{{ $invitation->id }}" class="nav-link d-none d-sm-block"><i class="ri-delete-bin-line"></i></a>
                                        @include('dashboards.admin.pages.authorization.modals.invitation.delete_invitation')
                                    </nav>
                                </div>
                            </li>
                        @empty
                            <div class="mt-5 mb-5 text-center">
                                No pending invitations...
                            </div>
                        @endforelse
                    </ul>
                </div>
            </div>
            @include('dashboards.admin.pages.authorization.modals.role.assign', [
                'source' => 'admin',
            ])
        </div>
    </div>
@endsection

@section('script')
    <script>
        function submitForm(choice, id) {
            var form = document.querySelector(`.deleteForm_` + id);

            if (choice === 'yes') {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'confirmation';
                input.value = 'yes';
                form.appendChild(input);
            } else {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'confirmation';
                input.value = 'no';
                form.appendChild(input);
            }

            form.submit();
        }
    </script>
@endsection
