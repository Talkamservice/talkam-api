<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title> Admin - TalkAM. </title>
    <meta name="Description" content="Find interesting posts and discussions on talkam.">
    <meta name="Author" content="TalkAM">
    <meta name="keywords" content="">

    <!-- Favicon -->
    <link rel="icon" href="{{ $admin_assets }}/images/brand-logos/favicon.ico" type="image/x-icon">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Choices JS -->
    <script src="{{ $admin_assets }}/libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="{{ $admin_assets }}/js/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="{{ $admin_assets }}/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="{{ $admin_assets }}/css/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="{{ $admin_assets }}/css/icons.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="{{ $admin_assets }}/libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="{{ $admin_assets }}/libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="{{ $admin_assets }}/libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="{{ $admin_assets }}/libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="{{ $admin_assets }}/libs/choices.js/public/assets/styles/choices.min.css">


    <link rel="stylesheet" href="{{ $admin_assets }}/libs/jsvectormap/css/jsvectormap.min.css">

    <link rel="stylesheet" href="{{ $admin_assets }}/libs/swiper/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('admin_assets/css/dynamic_select.css') }}">
    <!-- In your Blade file -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

    <style>
        .list-head-cont {
            opacity: 1 !important;
        }

        .list-head {
            color: #a3aed1;
        }

        .list-item {
            color: #FFF !important;
            transition: all 0.3s ease-in-out !important;
        }

        .list-item-icon,
        .list-angle {
            fill: #FFF !important;
            color: #FFF !important;

        }

        .list-item-label {
            color: #FFF !important;
        }

        .list-item:hover {
            background-color: #fff !important;
        }

        .list-item:hover .list-item-label {
            color: #000000 !important;
        }

        .list-item:hover .list-item-icon,
        .list-item:hover .list-angle {
            fill: #000000 !important;
            color: #000000 !important;
        }

        .list-item-sub:hover {
            color: #000000 !important;
        }

        .no-data-image {
            height: 50vh;
            width: 40%;
        }
    </style>
</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="{{ $admin_assets }}/images/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        @include('dashboards.admin.layout.includes.header')
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        @include('dashboards.admin.layout.includes.sidebar')
        <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid mt-2">
                @include('general.notifications.flash_messages')
            </div>
            @yield('content')
        </div>
        <!-- End::app-content -->

        <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="input-group">
                            <a href="javascript:void(0);" class="input-group-text" id="Search-Grid"><i
                                    class="fe fe-search header-link-icon fs-18"></i></a>
                            <input type="search" class="form-control border-0 px-2" placeholder="Search"
                                aria-label="Username">
                            <a href="javascript:void(0);" class="input-group-text" id="voice-search"><i
                                    class="fe fe-mic header-link-icon"></i></a>
                            <a href="javascript:void(0);" class="btn btn-light btn-icon" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fe fe-more-vertical"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Another action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                            <span class="search-tags"><i class="fe fe-user me-2"></i>People<a href="javascript:void(0)"
                                    class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                        </div>
                        <div class="my-4">
                            <p class="font-weight-semibold text-muted mb-2">Recent Search :</p>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="notifications.html"><span>Notifications</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="alerts.html"><span>Alerts</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                <a href="mail.html"><span>Mail</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="btn-group ms-auto">
                            <button class="btn btn-sm btn-primary-light">Search</button>
                            <button class="btn btn-sm btn-primary">Clear Recents</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer Start -->
        @include('dashboards.admin.layout.includes.footer')
        <!-- Footer End -->

    </div>

    <!-- Scroll To Top -->
    <div class="scrollToTop">
        <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
    </div>
    <div id="responsive-overlay"></div>
    <!-- Scroll To Top -->

    <script src="https://code.jquery.com/jquery-3.7.1.js" crossorigin="anonymous"></script>

    <!-- Popper JS -->
    <script src="{{ $admin_assets }}/libs/@popperjs/core/umd/popper.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="{{ $admin_assets }}/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Defaultmenu JS -->
    <script src="{{ $admin_assets }}/js/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="{{ $admin_assets }}/libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="{{ $admin_assets }}/js/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="{{ $admin_assets }}/libs/simplebar/simplebar.min.js"></script>
    <script src="{{ $admin_assets }}/js/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="{{ $admin_assets }}/libs/@simonwep/pickr/pickr.es5.min.js"></script>

    <!-- JSVector Maps JS -->
    <script src="{{ $admin_assets }}/libs/jsvectormap/js/jsvectormap.min.js"></script>

    <!-- JSVector Maps MapsJS -->
    <script src="{{ $admin_assets }}/libs/jsvectormap/maps/world-merc.js"></script>

    <!-- Apex Charts JS -->
    <script src="{{ $admin_assets }}/libs/apexcharts/apexcharts.min.js"></script>

    <!-- Chartjs Chart JS -->
    <script src="{{ $admin_assets }}/libs/chart.js/chart.min.js"></script>

    <!-- CRM-Dashboard -->
    <script src="{{ $admin_assets }}/js/crm-dashboard.js"></script>

    <!-- Custom JS -->
    <script src="{{ $admin_assets }}/js/custom.js"></script>


    <!-- Custom-Switcher JS -->
    <script src="{{ $admin_assets }}/js/custom-switcher.min.js"></script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <script src="{{ asset('admin_assets/js/dynamic_select.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                tags: true
            });
        });
    </script>
    <script>
        function classicEditorInit(id) {
            ClassicEditor
                .create(document.querySelector(id))
                .catch(error => {
                    console.error(error);
                });
        }
    </script>
    {{-- <script>
        $(document).ready(function() {
            $(".deleteNotificationBtn").on("click", function() {
                let notificationId = $(this).data("id");

                $.ajax({
                    type: "GET",
                    url: $(this).data("url"),
                    success: function(data) {
                        toastr.success("Notification removed successfully");
                        let item = ".notificationItem_" + notificationId
                        $(item).fadeOut();
                    },
                    error: function(error) {
                        toastr.error("Failed");
                    }
                });
            });

            $(".deleteAllNotification").on("click", function() {
                $.ajax({
                    type: "GET",
                    url: $(this).data("url"),
                    success: function(data) {
                        toastr.success("Notification removed successfully");
                        $(".allNotifcationDiv").fadeOut();
                        $("#header-notification-scroll").html(
                            `<li class="dropdown-item">
                                <div class="text-center">
                                    <h6 class="fw-semibold mt-3">No New Notifications</h6>
                                </div>
                            </li>`
                        )
                    },
                    error: function(error) {
                        toastr.error("Failed");
                    }
                });
            });
        });
    </script> --}}
     <script>
        // Deleting a single notification
        $('.deleteNotificationBtn').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('url'); // URL to delete the notification
            var notificationId = $(this).data('id'); // Notification ID

            // Perform AJAX request to delete the notification
            $.ajax({
                url: url,
                type: 'DELETE',
                success: function(response) {
                    if (response.success) {
                        // On success, remove the notification from the DOM
                        $('.notificationItem_' + notificationId).remove();

                        // Update the notification count and list dynamically
                        updateNotificationList(response.notifications, response.notification_count);

                        // Show success message
                        showMessage('success_message', response.success_message);
                    } else {
                        showMessage('error_message', response.error_message); // Show error message
                    }
                },
                error: function(xhr) {
                    console.error('Failed to delete notification:', xhr);
                    showMessage('error_message',
                    'Failed to delete notification.'); // Show error message
                }
            });
        });

        // Handle clicking the "Clear All" button
        $('.deleteAllNotification').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('url'); // URL to clear all notifications

            // Perform AJAX request to delete all notifications
            $.ajax({
                url: url,
                type: 'DELETE',
                success: function(response) {
                    if (response.success) {
                        // On success, clear all notifications from the DOM
                        $('#header-notification-scroll').empty(); // Remove all notifications

                        // Update the notification count and list dynamically
                        updateNotificationList(response.notifications, response.notification_count);

                        // Show success message
                        showMessage('success_message', response.success_message);
                    } else {
                        showMessage('error_message', response.error_message); // Show error message
                    }
                },
                error: function(xhr) {
                    console.error('Failed to clear all notifications:', xhr);
                    showMessage('error_message',
                    'Failed to clear all notifications.'); // Show error message
                }
            });
        });

        // Function to show success, error, or info messages
        function showMessage(type, message) {
            if (type === 'success_message') {
                if (message) {
                    showToast(message, 'success');
                }
            } else if (type === 'error_message') {
                if (message) {
                    showToast(message, 'error');
                }
            }
        }

        // Function to show toast messages
        function showToast(message, type) {
            var toastType = type === 'success' ? 'bg-success' : 'bg-danger';
            var toastHTML = `<div class="toast align-items-center text-white ${toastType} mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
            $('#toast-container').append(toastHTML); // Assuming you have a toast container with id 'toast-container'
            var toastElement = $('#toast-container .toast').last();
            var toast = new bootstrap.Toast(toastElement);
            toast.show();
        }

        // Function to update the notification list and count dynamically
        function updateNotificationList(notifications, count) {
            var notificationListHtml = '';

            // Build the notification list HTML dynamically
            if (notifications.length > 0) {
                notifications.forEach(function(notification) {
                    var data = notification.data; // Assuming `data` contains the notification details
                    notificationListHtml += `
                        <li class="dropdown-item notificationItem_${notification.id} allNotifcationDiv">
                            <div class="d-flex align-items-start">
                                <div class="pe-2">
                                    <span class="avatar avatar-md bg-success-transparent avatar-rounded"><i class="ti ti-clock fs-18"></i></span>
                                </div>
                                <div class="flex-grow-1 d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="mb-0 fw-semibold"><a href="#">${data.title}</a></p>
                                        <span class="text-muted fw-normal fs-12 header-notification-text">${data.message}</span>
                                    </div>
                                    <div>
                                        <a href="javascript:void(0);" data-id="${notification.id}" data-url="/notifications/${notification.id}/delete" class="min-w-fit-content text-muted me-1 dropdown-item-close1 deleteNotificationBtn"><i class="ti ti-x fs-16"></i></a>
                                    </div>
                                </div>
                            </div>
                        </li>`;
                });
            } else {
                notificationListHtml =
                    '<li class="dropdown-item"><div class="text-center"><h6 class="fw-semibold mt-3">No New Notifications</h6></div></li>';
            }

            // Update the notification list and count in the DOM
            $('#header-notification-scroll').html(notificationListHtml);
            $('#notifiation-data').text(count + ' Unread'); // Update the notification count display
        }
    </script>
    @yield('script')
</body>

</html>
