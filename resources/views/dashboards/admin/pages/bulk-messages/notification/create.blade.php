@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($notification) ? 'Edit' : 'Create' }} Notification</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.notifications.send-bulk-notification.index') }}">Notifications</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ isset($notification) ? 'Edit' : 'Create' }}
                        </li>
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
                        <form
                            action="{{ isset($notification) ? route('admin.notifications.send-bulk-notification.update', $notification->id) : route('admin.notifications.send-bulk-notification.store') }}"
                            method="POST">
                            @csrf
                            @isset($notification)
                                @method('patch')
                            @endisset

                            <div class="gy-4 mb-4">
                                <!-- Type Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="type" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Type</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="type" id="type" class="form-control" onchange="toggleRecipients(this.value)">
                                            <option readonly value="" >Select Option</option>
                                            <option value="Single" {{ (old('type') ?? ($notification->type ?? '')) == 'Single' ? 'selected' : '' }}>
                                                Single
                                            </option>
                                            <option value="Broadcast" {{ (old('type') ?? ($notification->type ?? '')) == 'Broadcast' ? 'selected' : '' }}>
                                                Broadcast
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Title Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="title" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Title</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="title" id="title"
                                            value="{{ old('title') ?? ($notification->title ?? '') }}"
                                            placeholder="Enter title">
                                    </div>
                                </div>

                                <!-- Recipients Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3" id="recipients-container" style="display:none;">
                                    <label for="recipients" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Recipients</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="user_id[]" id="recipients" class="form-control" multiple>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}"
                                                    {{ in_array($user->id, old('user_id', isset($notification) ? $notification->recipients()->pluck('user_id')->toArray() : [])) ? 'selected' : '' }}>
                                                    {{ $user->getName() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Message Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="message" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Message</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <textarea name="body" class="form-control" id="message" cols="30" rows="5">{!! old('body') ?? ($notification->body ?? '') !!}</textarea>
                                    </div>
                                </div>

                                <!-- Schedule Date Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="schedule_date" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Schedule Date (Optional)</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="datetime-local" class="form-control" name="schedule_date"
                                            id="schedule_date"
                                            value="{{ old('schedule_date') ?? ($notification->schedule_date ?? '') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer d-none border-top-0"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Select2 JavaScript and CSS for better multi-select functionality -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script>
        function toggleRecipients(type) {
            const recipientsContainer = document.getElementById('recipients-container');
            const recipientsSelect = $('#recipients');

            if (type === 'Single') {
                recipientsContainer.style.display = 'flex';
                recipientsSelect.select2({
                    tags: true,
                    tokenSeparators: [',', ' '],
                    closeOnSelect: false,
                    templateSelection: function(data, container) {
                        $(container).find('.select2-selection__choice__remove').html(
                            '<i class="ri-close-line" style="padding-right: 5px; cursor: pointer;"></i>'
                        );
                        return data.text;
                    }
                });
            } else {
                recipientsContainer.style.display = 'none';
            }
        }

        $(document).ready(function() {
            const initialType = $('#type').val();
            toggleRecipients(initialType);

            $('#recipients').select2({
                tags: true,
                tokenSeparators: [',', ' '],
                closeOnSelect: false,
                templateSelection: function(data, container) {
                    $(container).find('.select2-selection__choice__remove').html(
                        '<i class="ri-close-line" style="padding-left: 5px; cursor: pointer;"></i>'
                    );
                    return data.text;
                }
            });
        });
    </script>

@section('script')
    <script>
        classicEditorInit('#message');
    </script>
@endsection
@endsection
