<aside style="background-color: #0365A1;" class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div style="background-color: #0365A1;" class="main-sidebar-header">
        <a href="index.html" class="header-logo">
            {{-- <img src="{{ $admin_assets }}/images/authentication/logo_text.svg" alt="logo" class="desktop-logo"> --}}
            {{-- <img src="{{ $admin_assets }}/images/authentication/logo_text.png" alt="logo" class="toggle-logo"> --}}
            <img src="{{ $admin_assets }}/images/authentication/logo_white_text.svg" alt="logo" class="desktop-dark">
            {{-- <img src="{{ $admin_assets }}/images/authentication/logo_text.png" alt="logo" class="toggle-dark"> --}}
            {{-- <img src="{{ $admin_assets }}/images/authentication/logo_text.svg" alt="logo" class="desktop-white"> --}}
            {{-- <img src="{{ $admin_assets }}/images/authentication/logo_text.png" alt="logo" class="toggle-white"> --}}
        </a>
    </div>
    <!-- End::main-sidebar-header -->

    <!-- Start::main-sidebar -->
    <div class="main-sidebar " id="sidebar-scroll">

        <!-- Start::nav -->
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                    viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>
            <ul class="main-menu">
                <!-- Start::slide__category -->
                <li class="slide__category list-head-cont "><span class="category-name list-head ">Main</span></li>
                <!-- End::slide__category -->

                <!-- Start::slide -->
                <li class="slide">
                    <a href="{{ route('admin.home') }}" class="side-menu__item list-item">
                        <i class="bx bx-home side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label ">Dashboard</span>
                    </a>
                </li>
                <!-- End::slide -->

                <!-- Start::slide__category -->
                <li class="slide__category list-head-cont"><span class="category-name list-head">USER MANAGEMENT</span>
                </li>
                <!-- End::slide__category -->

                <!-- Start::slide -->
                <li class="slide">
                    <a href="{{ route('admin.users.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-user-plus side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Users</span>
                    </a>
                </li>

                <!-- End::slide -->

                <!-- Start::slide -->
                <li class="slide">
                    <a href="{{ route('admin.members.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-key side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Admins</span>
                    </a>
                </li>
                <!-- End::slide -->

                <!-- Start::slide__category -->
                <li class="slide__category list-head-cont"><span class="category-name list-head">CONTENT
                        MANAGEMENT</span></li>
                <!-- End::slide__category -->

                <li class="slide">
                    <a href="{{ route('admin.post-categories.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-folder-open side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Categories</span>
                    </a>
                </li>

                <!-- Start::slide__category -->
                <li class="slide__category list-head-cont"><span class="category-name list-head">SYSTEM
                        MANAGEMENT</span></li>
                <!-- End::slide__category -->

                <li class="slide">
                    <a href="{{ route('admin.guidelines.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-list-ul side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Community Guidelines</span>
                    </a>
                </li>

                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item list-item">
                        <i class="bx bx-notification side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Digital Outreach</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="{{ route('admin.notifications.send-bulk-notification.index') }}"
                                class="side-menu__item list-item list-item-sub">Notification</a>
                        </li>
                        <li class="slide">
                            <a href="{{ route('admin.announcements.index') }}"
                                class="side-menu__item list-item list-item-sub">Announcement</a>
                        </li>
                    </ul>
                </li>
                

                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item list-item">
                        <i class="bx bx-message-dots side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Report</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="{{ route('admin.reports.post.lists') }}"
                                class="side-menu__item list-item list-item-sub">Post</a>
                            <a href="{{ route('admin.reports.comment.lists') }}"
                                class="side-menu__item list-item list-item-sub">Comment</a>

                        </li>
                        <li class="slide has-sub">
                            <a href="javascript:void(0);" class="side-menu__item list-item">
                                {{-- <i class="bx bx-user-plus side-menu__icon list-item-icon"></i> --}}
                                <span class="side-menu__label list-item-label">Group</span>
                                <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <a href="{{ route('admin.reports.group.lists') }}"
                                        class="side-menu__item list-item list-item-sub">Group</a>
                                    <a href="{{ route('admin.reports.group.member.lists') }}"
                                        class="side-menu__item list-item list-item-sub">Members</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>

                <li class="slide">
                    <a href="{{ route('admin.terms-and-conditions.create') }}" class="side-menu__item list-item">
                        <i class="bx bx-list-ul side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Terms And Conditions</span>
                    </a>
                </li>


                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item list-item">
                        <i class="bx bx-question-mark side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">FAQs</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="{{ route('admin.faqs.index') }}"
                                class="side-menu__item list-item list-item-sub">FAQs</a>
                        </li>
                        <li class="slide">
                            <a href="{{ route('admin.faq-categories.index') }}"
                                class="side-menu__item list-item list-item-sub">Categories</a>
                        </li>
                    </ul>
                </li>

                <li class="slide">
                    <a href="{{ route('admin.privacy-policies.create') }}" class="side-menu__item list-item">
                        <i class="bx bx-low-vision side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Privacy Policy</span>
                    </a>
                </li>

                <!-- Start::slide -->
                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item list-item">
                        <i class="bx bx-lock-alt side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Authorization</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="{{ route('admin.authorization.roles.index') }}"
                                class="side-menu__item list-item list-item-sub">Roles</a>
                        </li>
                        <li class="slide">
                            <a href="{{ route('admin.authorization.permissions.index') }}"
                                class="side-menu__item list-item list-item-sub">Permissions</a>
                        </li>
                    </ul>
                </li>
                {{-- <!-- Start::slide -->
                 <li class="slide__category list-head-cont"><span class="category-name list-head">WEBSITE</span></li> --}}

                {{-- <li class="slide">
                    <a href="{{ route('admin.faqs.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-question-mark side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Faqs</span>
                    </a>
                </li> --}}

                {{-- <li class="slide">
                    <a href="{{ route('admin.terms-and-conditions.create') }}" class="side-menu__item list-item">
                        <i class="bx bx-list-ul side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Terms And Conditions</span>
                    </a>
                </li> --}}

                {{-- <li class="slide">
                    <a href="{{ route('admin.privacy-policies.create') }}" class="side-menu__item list-item">
                        <i class="bx bx-low-vision side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Privacy Policy</span>
                    </a>
                </li> --}}


                <li class="slide__category list-head-cont"><span class="category-name list-head">SETTINGS</span></li>

                <li class="slide">
                    <a href="{{ route('admin.activity-logs.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-history side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Activity Logs</span>
                    </a>
                </li>

                <li class="slide">
                    <a href="{{ route('admin.avatars.index') }}" class="side-menu__item list-item">
                        <i class="bx bx-user side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Avatars</span>
                    </a>
                </li>

                <li class="slide">
                    <a href="{{ route('admin.account-deactivation-requests.index') }}"
                        class="side-menu__item list-item">
                        <i class="bx bx-trash side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Deactivation Reqs</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                </li>

                {{-- <li class="slide">
                    <a href="javascript:void(0);" class="side-menu__item list-item">
                        <i class="bx bx-wrench side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Settings</span>
                        <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                    </a>
                </li> --}}
                <!-- End::slide -->
    </div>
    <!-- End::main-sidebar -->

</aside>
