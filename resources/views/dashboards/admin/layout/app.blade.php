<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title> Admin - Talkam. </title>
    <meta name="Description" content="Find interesting posts and discussions on talkam.">
    <meta name="Author" content="Talkam">
    <meta name="keywords" content="">

    <!-- Favicon -->
    <link rel="icon" href="{{ $admin_assets }}/images/brand-logos/favicon.ico" type="image/x-icon">

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

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
        crossorigin="anonymous"></script>

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

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
    <script>
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
    </script>
    @yield('script')
</body>

</html>
