@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Users</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Users</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header ">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" value="{{ request()->search }}" placeholder="Search...." name="search">
                        </div>
                        <div class="form-group me-2">
                            <label for="">Status</label>
                            <select name="status" class="form-control">
                                <option value="">Select Option</option>
                                @foreach ($statuses as $key => $status)
                                    <option value="{{ $key }}" {{ request()->status == $key ? 'selected' : '' }}>
                                        {{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group me-2">
                            <label for="">From</label>
                            <input class="form-control" type="date" value="{{ request()->from }}" placeholder="Search...." name="from">
                        </div>
                        <div class="form-group me-2">
                            <label for="">To</label>
                            <input class="form-control" type="date" value="{{ request()->to }}" placeholder="Search...." name="to">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Username</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center fw-semibold">
                                                <span class="avatar avatar-sm me-2 avatar-rounded">
                                                    <img src="{{ $user->avatarUrl() }}" alt="img">
                                                </span>{{ $user->username }}
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
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>

                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="{{ route('admin.users.show', $user->id) }}"><i class="ri-eye-line"></i> | View</a>
                                                    </li>
                                                    <li>
                                                        <form id="suspendUser_{{ $user->id }}" action="{{ route('admin.users.suspend', $user->id) }}" method="post" onsubmit="return confirm('Are you sure of this action?')"> @csrf
                                                            @if ($user->status == 'Active')
                                                                <input type="hidden" name="status" value="Inactive">
                                                                <a class="dropdown-item text-warning" href="#" onclick="$('#suspendUser_{{ $user->id }}').submit()"><i class="ri-close-line"></i> | Suspend </a>
                                                            @else
                                                                <input type="hidden" name="status" value="Active">
                                                                <a class="dropdown-item text-warning" href="#" onclick="$('#suspendUser_{{ $user->id }}').submit()"><i class="ri-check-line"></i> | Activate</a>
                                                            @endif
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form id="deleteUser_{{ $user->id }}" action="{{ route('admin.users.destroy', $user->id) }}" method="post" onsubmit="return confirm('Are you sure of this action?')">
                                                            @csrf
                                                            @method('delete')
                                                            <a class="dropdown-item text-danger" onclick="$('#deleteUser_{{ $user->id }}').submit()" href="#"><i class="ri-delete-bin-line"></i> | Delete</a>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $users->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
