@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ isset($notification) ? 'Edit' : 'Create' }} Notification</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a
                                href="{{ route('admin.notifications.send-bulk-notification.index') }}">Notifications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($notification) ? 'Edit' : 'Create' }}
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

                            <div class="row gy-4 mb-4">
                                <!-- Type Field -->
                                <div class="col-md-6">
                                    <label for="type" class="form-label">Type</label>
                                    <select name="type" id="type" class="form-control"
                                        onchange="toggleRecipients(this.value)">
                                        <option value="" disabled selected>Select Option</option>
                                        <option value="single"
                                            {{ (old('type') ?? ($notification->type ?? '')) == 'single' ? 'selected' : '' }}>
                                            Single</option>
                                        <option value="multiple"
                                            {{ (old('type') ?? ($notification->type ?? '')) == 'multiple' ? 'selected' : '' }}>
                                            Multiple</option>
                                        <option value="all"
                                            {{ (old('type') ?? ($notification->type ?? '')) == 'all' ? 'selected' : '' }}>
                                            All</option>
                                    </select>
                                </div>

                                <!-- Title Field -->
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" id="title"
                                        value="{{ old('title') ?? ($notification->title ?? '') }}"
                                        placeholder="Enter title">
                                </div>
                            </div>

                            <div class="row gy-4 mb-4">
                                <!-- Recipients Field -->
                                <div class="col-md-12" id="recipients-container" style="display:none;">
                                    <label for="recipients" class="form-label">Recipients</label>
                                    <select name="user_id[]" id="recipients" class="form-control" multiple>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}"
                                                {{ in_array($user->id, old('user_id', isset($notification) ? $notification->recipients()->pluck('user_id')->toArray() : [])) ? 'selected' : '' }}>
                                                {{ $user->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row gy-4 mb-4">
                                <!-- Message Field -->
                                <div class="col-md-12">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea name="message" class="form-control" id="message" cols="5" rows="6">{{ old('message') ?? ($notification->message ?? '') }}</textarea>
                                </div>
                            </div>

                            <div class="row gy-4 mb-4">
                                <!-- Schedule Date Field -->
                                <div class="col-md-6">
                                    <label for="schedule_date" class="form-label">Schedule Date (Optional)</label>
                                    <input type="datetime-local" class="form-control" name="schedule_date"
                                        id="schedule_date"
                                        value="{{ old('schedule_date') ?? ($notification->schedule_date ?? '') }}">
                                </div>

                                <!-- Status Field -->
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="" disabled selected>Select Option</option>
                                        <option value="Pending"
                                            {{ (old('status') ?? ($notification->status ?? '')) == 'Pending' ? 'selected' : '' }}>
                                            Pending</option>
                                        <option value="Published"
                                            {{ (old('status') ?? ($notification->status ?? '')) == 'Published' ? 'selected' : '' }}>
                                            Published</option>
                                    </select>
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
    <script src="https://cdn.tiny.cloud/1/9kceokxig3p7h7aj82ykjwy3ohrak2bq8wozjh90w23fr1mz/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script>

    <script>
        function toggleRecipients(type) {
            const recipientsContainer = document.getElementById('recipients-container');
            const recipientsSelect = $('#recipients');

            if (type === 'single' || type === 'multiple') {
                recipientsContainer.style.display = 'flex';

                // Adjust the multiple attribute
                recipientsSelect.attr('multiple', type === 'multiple');

                // Reinitialize Select2 with the correct settings
                recipientsSelect.select2({
                    tags: true,
                    tokenSeparators: [',', ' '],
                    closeOnSelect: false,
                    templateSelection: function(data, container) {
                        $(container).find('.select2-selection__choice__remove').html(
                            '<i class="fas fa-times" style="padding-left: 5px; cursor: pointer;"></i>'
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
                        '<i class="fas fa-times" style="padding-left: 5px; cursor: pointer;"></i>'
                    );
                    return data.text;
                }
            });
        });

        tinymce.init({
            selector: 'textarea#message',
            plugins: 'lists link image paste help wordcount',
            toolbar: 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
            menubar: false,
            height: 200,
            width: '100%',
            content_style: "body { font-family:Helvetica,Arial,sans-serif; font-size:14px }"
        });
    </script>
@endsection
