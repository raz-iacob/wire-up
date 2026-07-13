@include('errors.static-error', [
    'code' => 503,
    'title' => __('Down for maintenance'),
    'message' => __('We’re making some improvements. Please check back in a few minutes.'),
])
