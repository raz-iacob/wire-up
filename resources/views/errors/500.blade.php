@include('errors.static-error', [
    'code' => 500,
    'title' => __('Something went wrong'),
    'message' => __('An unexpected error occurred on our end. Please try again shortly.'),
])
