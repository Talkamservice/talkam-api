@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Bench Topics</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Business</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Bench Topics</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="alert alert-info" role="alert">
            These are the therapist specialties companies prioritise on the "Preview your therapist bench" screen.
            They are the same interest-topic categories therapists tag and the app matches on — featuring one here
            controls whether it appears on the bench, not whether therapists can offer it.
        </div>

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" placeholder="Search...." name="search"
                                value="{{ request('search') }}">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a href="{{ route('admin.bench-topics.create') }}" class="btn btn-primary btn-sm"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Bench order</th>
                                    <th scope="col">Specialty</th>
                                    <th scope="col">On bench</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topics as $topic)
                                    <tr>
                                        <td>{{ $topic->is_bench_featured ? $topic->bench_sort : '—' }}</td>
                                        <td>{{ $topic->name }}</td>
                                        <td>
                                            @if ($topic->is_bench_featured)
                                                <span class="badge bg-success-transparent">Featured</span>
                                            @else
                                                <span class="badge bg-secondary-transparent">Hidden</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($topic->status) }}-transparent">
                                                {{ $topic->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a aria-label="anchor" href="{{ route('admin.bench-topics.edit', $topic->id) }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i class="ri-edit-line"></i></a>

                                                @if ($topic->is_bench_featured)
                                                    <a data-bs-toggle="tooltip" title="Remove from bench"
                                                        class="dropdown-item text-danger" href="#"
                                                        onclick="openDeleteModal('{{ route('admin.bench-topics.destroy', $topic->id) }}')">
                                                        <i class="ri-close-circle-line"></i> | Remove from bench
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="alert alert-info text-center mb-0">No record found</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $topics->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.delete-modal')
@endsection
