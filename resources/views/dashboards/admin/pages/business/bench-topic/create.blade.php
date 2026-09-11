@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($topic) ? 'Edit' : 'Create' }} Bench Topic</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.bench-topics.index') }}">Bench Topics</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($topic) ? 'Edit' : 'Create' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->
        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        @unless (isset($topic))
                            <div class="alert alert-warning" role="alert">
                                Creating a specialty here adds it to the shared taxonomy — it also becomes selectable
                                by therapists and matchable in the app, not just on the bench.
                            </div>
                        @endunless
                        <form
                            action="{{ isset($topic) ? route('admin.bench-topics.update', $topic->id) : route('admin.bench-topics.store') }}"
                            method="POST"> @csrf
                            @isset($topic)
                                @method('patch')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="bench-topic-name" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Specialty</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="name" id="bench-topic-name"
                                            value="{{ old('name') ?? ($topic->name ?? '') }}" placeholder="e.g. Anxiety" required>
                                        <small class="text-muted">Shown to companies as a selectable specialty, and to therapists as an interest topic.</small>
                                    </div>
                                </div>

                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Show on bench</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12 d-flex align-items-center">
                                        <input type="hidden" name="is_bench_featured" value="0">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" name="is_bench_featured"
                                                id="bench-topic-featured" value="1"
                                                {{ (old('is_bench_featured', $topic->is_bench_featured ?? true)) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="bench-topic-featured">Appears on the company bench screen</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="bench-topic-order" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Bench order</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" min="0" class="form-control" name="bench_sort" id="bench-topic-order"
                                            value="{{ old('bench_sort') ?? ($topic->bench_sort ?? 0) }}" placeholder="0">
                                        <small class="text-muted">Lower numbers appear first. Only used while featured.</small>
                                    </div>
                                </div>

                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="bench-topic-status" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="bench-topic-status" class="form-control">
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}"
                                                    {{ (old('status') ?? ($topic->status ?? \App\Constants\General\StatusConstants::ACTIVE)) == $key ? 'selected' : '' }}>
                                                    {{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer d-none border-top-0">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
