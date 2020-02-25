<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? $message ?? '' }}</title>

<style>
    body {
        font-family: sans-serif;
        font-size: calc(16px + 0.5vw);
        line-height: calc(32px + 0.5vw);
        margin: 5vh 5vw;
        place-items: center;
        display: grid;
        width: 90vw;
        height: 80vh;
    }
    .button {
        border-radius: 15vw;
        color: #ffffff;
        display: inline-block;
        font-size: calc(24px + 0.5vw);
        line-height: initial;
        padding: calc(8px + 1%) calc(16px + 2%);
        text-align: center;
        text-decoration: none;
        width: auto;
    }
    .cancel {
        background-color: #ff0000;
    }
    .button:disabled {
        background-color: #808080;
    }
</style>
</head>
<body>
    <div>@markdown ($message)</div>

    @isset ($continue)
        <a href="{{ $continue }}">Yes, please proceed anyways.</a>
    @endisset

    @isset ($cancel)
        <button class="button cancel" onclick="cancel()" id="disableOnTimeUp">Cancel</button>
        <div id="app"></div>{{-- timer shows up here --}}

        @include ('css.timer')

        @include ('js.timer', ['link' => $cancel])
    @endisset
</body>
</html>
