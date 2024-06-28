<header class="app-header">

    <!-- Start::main-header-container -->
    <div class="main-header-container container-fluid">

        <!-- Start::header-content-left -->
        <div class="header-content-left">

            <!-- Start::header-element -->
            <div class="header-element">
                <div class="horizontal-logo">
                    <a href="index.html" class="header-logo">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="desktop-logo">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="toggle-logo">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="desktop-dark">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="toggle-dark">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="desktop-white">
                        <img src="{{ $admin_assets }}/images/authentication/logo.svg" alt="logo" class="toggle-white">
                    </a>
                </div>
            </div>
            <!-- End::header-element -->

            <!-- Start::header-element -->
            <div class="header-element">
                <!-- Start::header-link -->
                <a aria-label="Hide Sidebar" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" data-bs-toggle="sidebar" href="javascript:void(0);"><span></span></a>
                <!-- End::header-link -->
            </div>
            <!-- End::header-element -->

        </div>
        <!-- End::header-content-left -->

        <!-- Start::header-content-right -->
        <div class="header-content-right">

            <!-- Start::header-element -->
            {{-- <div class="header-element header-search">
                <!-- Start::header-link -->
                <a href="javascript:void(0);" class="header-link" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bx bx-search-alt-2 header-link-icon"></i>
                </a>
                <!-- End::header-link -->
            </div> --}}
            <!-- End::header-element -->

            <!-- Start::header-element -->
            <div class="header-element notifications-dropdown">
                <!-- Start::header-link|dropdown-toggle -->
                <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="messageDropdown" aria-expanded="false">
                    <i class="bx bx-bell header-link-icon"></i>
                    {{-- <span class="badge bg-secondary rounded-pill header-icon-badge pulse pulse-secondary" id="notification-icon-badge">5</span> --}}
                </a>
                <!-- End::header-link|dropdown-toggle -->
                <!-- Start::main-header-dropdown -->
                <div class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="mb-0 fs-17 fw-semibold">Notifications</p>

                            <!-- Example single danger button -->
                            <div class="btn-group">
                                <i class="ri-menu-line dropdown-toggle" data-bs-toggle="dropdown"></i>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item text-danger deleteAllNotification" href="#" data-url="{{ route("admin.notifications.clear-all") }}">Clear All</a>
                                </div>
                            </div>
                            {{-- <span class="badge bg-secondary-transparent" id="notifiation-data">{{ $global_notifications->count() }} Unread</span> --}}
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul class="list-unstyled mb-0" id="header-notification-scroll">
                        @forelse ($global_notifications as $notification)
                            @php
                                $data = $notification->data;
                            @endphp
                            <li class="dropdown-item notificationItem_{{ $notification->id }} allNotifcationDiv">
                                <div class="d-flex align-items-start">
                                    <div class="pe-2">
                                        <span class="avatar avatar-md bg-success-transparent avatar-rounded"><i class="ti ti-clock fs-18"></i></span>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-between">
                                        <div>
                                            <p class="mb-0 fw-semibold"><a href="#">{{ $data['title'] ?? 'N/A' }}</a></p>
                                            <span class="text-muted fw-normal fs-12 header-notification-text">{{ $data['message'] ?? 'N/A' }}</span>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" data-id="{{ $notification->id }}" data-url="{{ route('admin.notifications.destroy', $notification->id) }}"
                                                class="min-w-fit-content text-muted me-1 dropdown-item-close1 deleteNotificationBtn"><i class="ti ti-x fs-16"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="dropdown-item">
                                <div class="text-center">
                                    <h6 class="fw-semibold mt-3">No New Notifications</h6>
                                </div>
                            </li>
                        @endforelse
                    </ul>
                    {{-- <div class="p-3 empty-header-item1 border-top">
                        <div class="d-grid">
                            <a href="#" class="btn btn-primary">View All</a>
                        </div>
                    </div> --}}
                </div>
                <!-- End::main-header-dropdown -->
            </div>
            <!-- End::header-element -->


            <!-- Start::header-element -->
            <div class="header-element">
                <!-- Start::header-link|dropdown-toggle -->
                <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <div class="d-flex align-items-center">
                        <div class="me-sm-2 me-0">
                            <img src="{{ auth()->user()->avatarUrl() }}" alt="img" width="32" height="32" class="rounded-circle">
                        </div>
                        <div class="d-sm-block d-none">
                            <p class="fw-semibold mb-0 lh-1">{{ auth()->user()->name }}</p>
                            <span class="op-7 fw-normal d-block fs-11">{{ auth()->user()->role }}</span>
                        </div>
                    </div>
                </a>
                <!-- End::header-link|dropdown-toggle -->
                <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end" aria-labelledby="mainHeaderProfile">
                    <li><a class="dropdown-item d-flex" href="{{ route("admin.profile.index") }}"><i class="ti ti-user-circle fs-18 me-2 op-7"></i>Profile</a></li>
                    {{-- <li><a class="dropdown-item d-flex" href="#"><i class="ti ti-adjustments-horizontal fs-18 me-2 op-7"></i>Settings</a></li> --}}
                    {{-- <li><a class="dropdown-item d-flex" href="#"><i class="ti ti-headset fs-18 me-2 op-7"></i>Support</a></li> --}}
                    <li><a class="dropdown-item d-flex" href="#" onclick="return $('#header_logout').submit();"><i class="ti ti-logout fs-18 me-2 op-7"></i>Log Out</a></li>
                    <form action="{{ route('logout') }}" onsubmit="return confirm('Are you sure of this action?')" method="post" id="header_logout"> @csrf </form>
                </ul>
            </div>
            <!-- End::header-element -->


        </div>
        <!-- End::header-content-right -->

    </div>
    <!-- End::main-header-container -->

</header>
