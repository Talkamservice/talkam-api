@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Custom Plan Quote Requests</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Business</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Custom Plan Quote Requests</li>
                    </ol>
                </nav>
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
                            <input class="form-control" type="text" placeholder="Company or email...." name="search"
                                value="{{ request('search') }}">
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
                                    <th scope="col">#</th>
                                    <th scope="col">Company</th>
                                    <th scope="col">Requested by</th>
                                    <th scope="col">Team size</th>
                                    <th scope="col">Notes</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Requested</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requests as $request)
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $request->organization?->name ?? '—' }}</td>
                                        <td>
                                            {{ $request->requestedBy?->getName() ?? '—' }}
                                            <div class="text-muted fs-12">{{ $request->email }}</div>
                                        </td>
                                        <td>{{ $request->team_size ? number_format($request->team_size) : '—' }}</td>
                                        <td>{{ $request->notes ? str_limit($request->notes, 60) : '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $request->status === 'contacted' ? 'success' : 'warning' }}-transparent">
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $request->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            @if ($request->status !== 'contacted')
                                                <form action="{{ route('admin.custom-plan-quote-requests.mark-contacted', $request->id) }}" method="post">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success-light">
                                                        <i class="ri-check-line"></i> Mark contacted
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted fs-12">
                                                    {{ optional($request->contacted_at)->format('Y-m-d h:i A') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <div class="alert alert-info text-center mb-0">No record found</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $requests->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
