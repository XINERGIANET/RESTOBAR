@props(['pageTitle' => 'Page', 'crumbs' => null, 'breadcrumbs' => null, 'iconHtml' => null])

@php
    use App\Models\MenuOption;
    use App\Helpers\MenuHelper;
    use Illuminate\Http\Request as HttpRequest;

    $currentPath = '/' . trim(request()->path(), '/');
    $currentRouteName = optional(request()->route())->getName();
    $breadcrumbViewId = request()->query('view_id');
    $branchViewId = request()->query('branch_view_id') ?? session('branch_view_id');
    $crumbList = $crumbs
        ?? $breadcrumbs
        ?? $attributes->get('crumbs')
        ?? $attributes->get('breadcrumbs');
    $resolveViewIdForUrl = function ($url) {
        if (!$url || $url === '#') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['view_id'])) {
                return $query['view_id'];
            }
        }

        $path = $parts['path'] ?? null;
        if (!$path) {
            return null;
        }

        $routeName = null;
        try {
            $matched = app('router')->getRoutes()->match(HttpRequest::create($path));
            $routeName = $matched?->getName();
        } catch (\Exception $e) {
            $routeName = null;
        }

        if ($routeName) {
            $menuOption = MenuOption::query()
                ->where('status', 1)
                ->where('action', $routeName)
                ->first();
            if ($menuOption?->view_id) {
                return $menuOption->view_id;
            }
        }

        $normalizedPath = ltrim($path, '/');
        $menuOption = MenuOption::query()
            ->where('status', 1)
            ->where(function ($query) use ($path, $normalizedPath) {
                $query->orWhere('action', $path)
                    ->orWhere('action', $normalizedPath);
            })
            ->first();

        return $menuOption?->view_id;
    };

    $replaceViewId = function ($url, $viewId) {
        if (!$url || $url === '#' || $viewId === null || $viewId === '') {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['view_id'] = $viewId;
        $queryString = http_build_query($query);

        $schemeHost = '';
        if (!empty($parts['scheme'])) {
            $schemeHost = $parts['scheme'] . '://' . ($parts['host'] ?? '');
            if (!empty($parts['port'])) {
                $schemeHost .= ':' . $parts['port'];
            }
        }

        $pathPart = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $schemeHost . $pathPart . ($queryString ? '?' . $queryString : '') . $fragment;
    };

    $appendViewId = function ($url) use ($breadcrumbViewId, $branchViewId, $resolveViewIdForUrl, $replaceViewId) {
        if ((!$breadcrumbViewId && !$branchViewId) || !$url) {
            return $url;
        }
        $viewIdForUrl = $resolveViewIdForUrl($url) ?: $breadcrumbViewId;
        if ($branchViewId) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            if ($path && preg_match('#/sucursales$#', $path)) {
                $viewIdForUrl = $branchViewId;
            }
        }
        return $replaceViewId($url, $viewIdForUrl);
    };

    $menuOption = MenuOption::query()
        ->where('status', 1)
        ->where(function ($query) use ($currentPath, $currentRouteName) {
            if ($currentRouteName) {
                $query->orWhere('action', $currentRouteName);
            }
            $query->orWhere('action', $currentPath)
                ->orWhere('action', ltrim($currentPath, '/'));
        })
        ->first();

    $menuIcon = $menuOption?->icon ? MenuHelper::getIconSvg($menuOption->icon) : null;
    $queryIcon = request()->query('icon');
    $queryIcon = is_string($queryIcon) && preg_match('/^ri-[a-z0-9-]+$/', $queryIcon) ? $queryIcon : null;
    $queryIconHtml = $queryIcon ? '<i class="' . $queryIcon . '"></i>' : null;
    $pageIcon = $iconHtml ?: $queryIconHtml ?: $menuIcon;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-2">
        @if ($pageIcon)
            <span class="text-gray-500 dark:text-gray-400">{!! $pageIcon !!}</span>
        @endif
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
            {{ $pageTitle }}
        </h2>
    </div>
    <nav>
        @if (!empty($crumbList))
            <ol class="flex items-center gap-1.5">
                <li>
                    <a
                        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        href="{{ $appendViewId(url('/')) }}"
                    >
                        Home
                        <svg
                            class="stroke-current"
                            width="17"
                            height="16"
                            viewBox="0 0 17 16"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                stroke=""
                                stroke-width="1.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </a>
                </li>
                @foreach ($crumbList as $index => $crumb)
                    @php
                        $isLast = $index === array_key_last($crumbList);
                        $label = $crumb['label'] ?? $crumb['name'] ?? '';
                        $url = $crumb['url'] ?? $crumb['href'] ?? null;
                        $url = $appendViewId($url);
                    @endphp
                    <li class="text-sm {{ $isLast ? 'text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400' }}">
                        @if (!$isLast && $url)
                            <a class="inline-flex items-center gap-1.5" href="{{ $url }}">
                                {{ $label }}
                                <svg
                                    class="stroke-current"
                                    width="17"
                                    height="16"
                                    viewBox="0 0 17 16"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                        stroke=""
                                        stroke-width="1.2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </a>
                        @else
                            {{ $label }}
                        @endif
                    </li>
                @endforeach
            </ol>
        @else
            <ol class="flex items-center gap-1.5">
                <li>
                    <a
                        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        href="{{ $appendViewId(url('/')) }}"
                    >
                        Home
                        <svg
                            class="stroke-current"
                            width="17"
                            height="16"
                            viewBox="0 0 17 16"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                stroke=""
                                stroke-width="1.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </a>
                </li>
                <li class="text-sm text-gray-800 dark:text-white/90">
                    {{ $pageTitle }}
                </li>
            </ol>
        @endif
    </nav>
</div>
