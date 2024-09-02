<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title> Admin - Talkam. </title>
    <meta name="Description" content="Find interesting posts and discussions on talkam.">
    <meta name="Author" content="Talkam">
    <meta name="keywords" content="admin,admin dashboard,admin panel,admin template,bootstrap,clean,dashboard,flat,jquery,modern,responsive,premium admin templates,responsive admin,ui,ui kit.">

    <!-- Favicon -->
    <link rel="icon" href="{{ $admin_assets }}/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script src="{{ $admin_assets }}/js/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="{{ $admin_assets }}/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="{{ $admin_assets }}/css/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="{{ $admin_assets }}/css/icons.min.css" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">


    <style>
        .sign-in-btn {
            background-color: #0365A1;
            color: #FFF;
            transition: all 0.3s ease-in-out;
        }

        .sign-in-btn:hover {
            background-color: #0365A1;
            color: #FFF
        }

        .card-shadow {
            box-shadow: 0 0 12px 0 #00000033 !important;
        }

        .reduce_card_body {
            padding-top: 1.5rem !important;
        }
    </style>
    @yield('style')

</head>

<body style="background-color: white">

    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
                <div class="card custom-card card-shadow">
                    <div class="mt-5 d-flex justify-content-center">
                        <a href="{{ route('web.index') }}">
                            <img style="height: 3rem;" src="{{ $admin_assets }}/images/authentication/logo_text.svg" alt="logo" class="desktop-logo">
                            <img style="height: 3rem;" src="{{ $admin_assets }}/images/authentication/logo_text.svg" alt="logo" class="desktop-dark">
                        </a>
                    </div>
                    @yield('content')
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="{{ $admin_assets }}/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password JS -->
    <script src="{{ $admin_assets }}/js/show-password.js"></script>
    @yield('script')
</body>

</html>
