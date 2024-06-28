@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Account Deactivation Request</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Account Deactivation Request</a></li>
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
                <div class="card-header d-flex justify-content-between">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" placeholder="Search...." name="search">
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
                                    <th scope="col">S/N</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Reason</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($deactivation_requests as $deactivation_request)
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $deactivation_request->email }}</td>
                                        <td>{{ str_limit($deactivation_request->reason) }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($deactivation_request->status) }}-transparent">
                                                {{ $deactivation_request->status }}
                                            </span>
                                        </td>
                                        <td>{{ $deactivation_request->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <form action="{{ route('admin.account-deactivation-requests.submit', $deactivation_request->id) }}" method="post" onsubmit="return confirm('Are you sure of this action?')"> @csrf
                                                    <input type="hidden" name="deactivation_request_id" value="{{ $deactivation_request->id }}">
                                                    <button type="submit" class="btn btn-wave waves-effect waves-light btn-sm btn-danger-light" {{ $deactivation_request->status == "Disabled" ? "disabled" : "" }}>Approve</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <div class="alert alert-info text-center">
                                        No record found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            Showing 5 Entries
                        </div>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>
@endsection
