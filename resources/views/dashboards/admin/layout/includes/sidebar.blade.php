<aside style="background-color: #0365A1;" class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div style="background-color: #0365A1;" class="main-sidebar-header">
        <a href="{{ route('admin.home') }}" class="header-logo">
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>
            <ul class="main-menu">
                <!-- Start::slide__category -->
                <li class="slide__category list-head-cont "><span class="category-name list-head ">Main</span></li>
                <!-- End::slide__category -->

                <!-- Dashboard -->
                <li class="slide">
                    <a href="{{ route('admin.home') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.home' ? 'active' : '' }}">
                        <i class="bx bx-home side-menu__icon list-item-icon"></i>
                        <span class="side-menu__label list-item-label">Dashboard</span>
                    </a>
                </li>

                @canAny(slugPermission('read user'), slugPermission('read admin'))
                    <li class="slide__category list-head-cont"><span class="category-name list-head">USER MANAGEMENT</span>
                    </li>

                    @can(slugPermission('read user'))
                        <li class="slide">
                            <a href="{{ route('admin.users.index') }}" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.users.index', 'admin.users.create', 'admin.users.show', 'admin.users.edit']) ? 'active' : '' }}">
                                <i class="bx bx-user-plus side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Users</span>
                            </a>
                        </li>
                    @endcan

                    @can(slugPermission('read admin'))
                        <li class="slide">
                            <a href="{{ route('admin.members.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.members.index' ? 'active' : '' }}">
                                <i class="bx bx-key side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Admins</span>
                            </a>
                        </li>
                    @endcan

                @endcanAny

                @can(slugPermission('read category'))
                    <li class="slide__category list-head-cont"><span class="category-name list-head">CONTENT
                            MANAGEMENT</span></li>

                    <li class="slide">
                        <a href="{{ route('admin.post-categories.index') }}" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.post-categories.index', 'admin.post-categories.create', 'admin.post-categories.show', 'admin.post-categories.edit']) ? 'active' : '' }}">
                            <i class="bx bx-folder-open side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Categories</span>
                        </a>
                    </li>
                @endcan

                @canAny(slugPermission('read plan'), slugPermission('read promotion'))
                    <!-- Start::slide__category -->
                    <li class="slide__category list-head-cont"><span class="category-name list-head">FINANCE</span></li>
                    <!-- End::slide__category -->

                    <li class="slide has-sub">
                        <a href="javascript:void(0);" class="side-menu__item list-item">
                            <i class="bx bx-dollar-circle side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Billings</span>
                            <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                        </a>
                        <ul class="slide-menu child1">
                            <li class="slide">
                                <a href="{{ route('admin.plans.index') }}" class="side-menu__item list-item list-item-sub">Plans</a>
                            </li>
                        </ul>
                    </li>

                    @can(slugPermission('read promotion'))
                        <li class="slide has-sub">
                            <a href="javascript:void(0);" class="side-menu__item list-item">
                                <i class="bx bx-dollar-circle side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Ads Manaagement</span>
                                <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <a href="{{ route('admin.promotions.index') }}" class="side-menu__item list-item list-item-sub">All</a>
                                </li>
                            </ul>
                        </li>
                    @endcan
                @endcanAny

                @can(slugPermission('read guideline'))
                    <li class="slide__category list-head-cont"><span class="category-name list-head">SYSTEM
                            MANAGEMENT</span></li>
                    <!-- End::slide__category -->
                @endcan

                @canAny(slugPermission('read notification'), slugPermission('read announcement'))
                    <li class="slide has-sub">
                        <a href="javascript:void(0);" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.notifications.send-bulk-notification.index', 'admin.announcements.index']) ? 'active' : '' }}">
                            <i class="bx bx-notification side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Digital Outreach</span>
                            <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                        </a>
                        <ul class="slide-menu child1">
                            <li class="slide">
                                <a href="{{ route('admin.notifications.send-bulk-notification.index') }}"
                                    class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.notifications.send-bulk-notification.index', 'admin.notifications.send-bulk-notification.create, admin.notifications.send-bulk-notification.edit']) ? 'active' : '' }}">Notification</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.announcements.index') }}" class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.announcements.index', 'admin.announcements.create', 'admin.announcements.edit']) ? 'active' : '' }}">Announcement</a>
                            </li>
                        </ul>
                    </li>
                @endcanAny

                @can(slugPermission('read report'))
                    <li class="slide has-sub">
                        <a href="javascript:void(0);" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.reports.post.lists', 'admin.reports.comment.lists', 'admin.reports.group.lists', 'admin.reports.group.member.lists']) ? 'active' : '' }}">
                            <i class="bx bx-message-dots side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Report</span>
                            <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                        </a>
                        <ul class="slide-menu child1">
                            <li class="slide">
                                <a href="{{ route('admin.reports.post.lists') }}" class="side-menu__item list-item list-item-sub {{ Route::currentRouteName() == 'admin.reports.post.lists' ? 'active' : '' }}">General
                                    Post</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.reports.comment.lists') }}" class="side-menu__item list-item list-item-sub {{ Route::currentRouteName() == 'admin.reports.comment.lists' ? 'active' : '' }}">General
                                    Comment</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.reports.group.lists') }}" class="side-menu__item list-item list-item-sub {{ Route::currentRouteName() == 'admin.reports.group.lists' ? 'active' : '' }}">Group
                                    Post</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.reports.group.member.lists') }}" class="side-menu__item list-item list-item-sub {{ Route::currentRouteName() == 'admin.reports.group.member.lists' ? 'active' : '' }}">Group
                                    Members</a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @can(slugPermission('read term and condition'))
                    <li class="slide">
                        <a href="{{ route('admin.guidelines.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.guidelines.index' ? 'active' : '' }}">
                            <i class="bx bx-list-ul side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Community Guidelines</span>
                        </a>
                    </li>
                    <li class="slide">
                        <a href="{{ route('admin.terms-and-conditions.create') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.terms-and-conditions.create' ? 'active' : '' }}">
                            <i class="bx bx-list-ul side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Terms And Conditions</span>
                        </a>
                    </li>
                    <li class="slide">
                        <a href="{{ route('admin.waitlists.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.waitlists.index' ? 'active' : '' }}">
                            <i class="bx bx-list-check side-menu__icon list-item-icon"></i> <!-- Icon for waitlists -->
                            <span class="side-menu__label list-item-label">Waitlist</span>
                        </a>
                    </li>                    
                @endcan

                @can(slugPermission('read faq'))
                    <li class="slide has-sub">
                        <a href="javascript:void(0);" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.faqs.index', 'admin.faq-categories.index']) ? 'active' : '' }}">
                            <i class="bx bx-question-mark side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">FAQs</span>
                            <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                        </a>
                        <ul class="slide-menu child1">
                            <li class="slide">
                                <a href="{{ route('admin.faqs.index') }}" class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.faqs.index', 'admin.faqs.create', 'admin.faqs.show', 'admin.faqs.edit']) ? 'active' : '' }}">FAQs</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.faq-categories.index') }}"
                                    class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.faq-categories.index', 'admin.faq-categories.create', 'admin.faq-categories.show', 'admin.faq-categories.edit']) ? 'active' : '' }}">FAQ
                                    Categories</a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @can(slugPermission('read privacy policy'))
                    <li class="slide">
                        <a href="{{ route('admin.privacy-policies.create') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.privacy-policies.create' ? 'active' : '' }}">
                            <i class="bx bx-low-vision side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Privacy Policy</span>
                        </a>
                    </li>
                @endcan

                @can(slugPermission('read feedbacks'))
                    <li class="slide">
                        <a href="{{ route('admin.feedbacks.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.feedbacks.index' ? 'active' : '' }}">
                            <i class="bx bx-message-dots side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Feedbacks</span>
                        </a>
                    </li>
                @endcan

                <!-- Start::slide -->
                @hasrole('Sudo')
                    <li class="slide has-sub">
                        <a href="javascript:void(0);" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.authorization.roles.index', 'admin.authorization.permissions.index']) ? 'active' : '' }}">
                            <i class="bx bx-lock-alt side-menu__icon list-item-icon"></i>
                            <span class="side-menu__label list-item-label">Authorization</span>
                            <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                        </a>
                        <ul class="slide-menu child1">
                            <li class="slide">
                                <a href="{{ route('admin.authorization.roles.index') }}"
                                    class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.authorization.roles.index', 'admin.authorization.roles.create', 'admin.authorization.roles.show', 'admin.authorization.roles.edit']) ? 'active' : '' }}">Roles</a>
                            </li>
                            <li class="slide">
                                <a href="{{ route('admin.authorization.permissions.index') }}"
                                    class="side-menu__item list-item list-item-sub {{ in_array(Route::currentRouteName(), ['admin.authorization.permissions.index', 'authorization.permissions.create', 'authorization.permissions', 'authorization.permissions']) ? 'active' : '' }}">Permissions</a>
                            </li>
                        </ul>
                    </li>
                @endhasrole

                @canAny(slugPermission('read notification'), slugPermission('read announcement'))
                    <li class="slide__category list-head-cont"><span class="category-name list-head">SETTINGS</span></li>

                    @can(slugPermission('read activity logs'))
                        <li class="slide">
                            <a href="{{ route('admin.activity-logs.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.activity-logs.index' ? 'active' : '' }}">
                                <i class="bx bx-history side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Activity Logs</span>
                            </a>
                        </li>
                    @endcan

                    @can(slugPermission('read avatar'))
                        <li class="slide">
                            <a href="{{ route('admin.avatars.index') }}" class="side-menu__item list-item {{ in_array(Route::currentRouteName(), ['admin.avatars.index', 'admin.avatars.index.create', 'admin.avatars.index.show', 'admin.avatars.index.edit']) ? 'active' : '' }}">
                                <i class="bx bx-user side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Avatars</span>
                            </a>
                        </li>
                    @endcan

                    @can(slugPermission('read deactivation requests'))
                        <li class="slide">
                            <a href="{{ route('admin.account-deactivation-requests.index') }}" class="side-menu__item list-item {{ Route::currentRouteName() == 'admin.account-deactivation-requests.index' ? 'active' : '' }}">
                                <i class="bx bx-trash side-menu__icon list-item-icon"></i>
                                <span class="side-menu__label list-item-label">Deactivation Reqs</span>
                                <i class="fe fe-chevron-right side-menu__angle list-angle"></i>
                            </a>
                        </li>
                    @endcan
                @endcanAny
            </ul>
        </nav>
        <!-- End::slide -->
    </div>
    <!-- End::main-sidebar -->

</aside>
