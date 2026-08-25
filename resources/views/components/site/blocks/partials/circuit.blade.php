@props(['uid', 'intensity' => 'subtle', 'color' => null])

<div
    @class(['wire-circuit', 'wire-circuit-bold' => $intensity === 'bold'])
    aria-hidden="true"
    @if ($color !== '') style="--wire-fx:{{ $color }}" @endif
>
    <svg viewBox="0 0 1600 600" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
            <radialGradient id="wc-bloom-{{ $uid }}">
                <stop class="wc-stop" offset="0%" stop-opacity="0.9" />
                <stop class="wc-stop" offset="35%" stop-opacity="0.3" />
                <stop class="wc-stop" offset="100%" stop-opacity="0" />
            </radialGradient>
            <pattern id="wc-grid-{{ $uid }}" width="40" height="40" patternUnits="userSpaceOnUse">
                <path class="wc-gridline" d="M40 0H0V40" />
            </pattern>
            <path
                id="wc-{{ $uid }}-t1"
                pathLength="1000"
                d="M-40 120L256 120A24 24 0 0 1 280 144L280 216A24 24 0 0 0 304 240L576 240A24 24 0 0 0 600 216L600 104A24 24 0 0 1 624 80L896 80A24 24 0 0 1 920 104L920 296A24 24 0 0 0 944 320L1240 320"
            />
            <path
                id="wc-{{ $uid }}-t2"
                pathLength="1000"
                d="M-40 480L176 480A24 24 0 0 0 200 456L200 424A24 24 0 0 1 224 400L456 400A24 24 0 0 1 480 424L480 496A24 24 0 0 0 504 520L856 520A24 24 0 0 0 880 496L880 464A24 24 0 0 1 904 440L1200 440"
            />
            <path
                id="wc-{{ $uid }}-t3"
                pathLength="1000"
                d="M-40 280L96 280A24 24 0 0 1 120 304L120 336A24 24 0 0 0 144 360L336 360A24 24 0 0 0 360 336L360 224A24 24 0 0 1 384 200L696 200A24 24 0 0 1 720 224L720 256A24 24 0 0 0 744 280L1040 280"
            />
            <path id="wc-{{ $uid }}-t4" pathLength="1000" d="M640 -40L640 416A24 24 0 0 0 664 440L1000 440" />
            <path
                id="wc-{{ $uid }}-t5"
                pathLength="1000"
                d="M1640 240L1144 240A24 24 0 0 0 1120 264L1120 376A24 24 0 0 0 1144 400L1320 400"
            />
            <path
                id="wc-{{ $uid }}-t6"
                pathLength="1000"
                d="M320 640L320 504A24 24 0 0 1 344 480L536 480A24 24 0 0 0 560 456L560 144A24 24 0 0 1 584 120L1000 120"
            />
            <path
                id="wc-{{ $uid }}-t7"
                pathLength="1000"
                d="M-40 40L376 40A24 24 0 0 1 400 64L400 136A24 24 0 0 0 424 160L520 160"
            />
            <path
                id="wc-{{ $uid }}-t8"
                pathLength="1000"
                d="M1640 480L1464 480A24 24 0 0 1 1440 456L1440 384A24 24 0 0 0 1416 360L1280 360"
            />
            <path id="wc-{{ $uid }}-t9" pathLength="1000" d="M1640 80L1344 80A24 24 0 0 0 1320 104L1320 160" />
            <path id="wc-{{ $uid }}-t10" pathLength="1000" d="M-40 600L176 600A24 24 0 0 0 200 576L200 520" />
        </defs>

        <rect class="wc-grid" width="1600" height="600" fill="url(#wc-grid-{{ $uid }})" />

        <g class="wc-sub">
            <path d="M-40 80L136 80A24 24 0 0 1 160 104L160 176A24 24 0 0 0 184 200L320 200" />
            <path d="M1640 600L1504 600A24 24 0 0 1 1480 576L1480 480" />
            <path d="M80 640L80 560" />
            <path d="M1640 400L1504 400A24 24 0 0 1 1480 376L1480 280" />
            <path d="M840 640L840 584A24 24 0 0 1 864 560L1120 560" />
            <path d="M-40 360L16 360A24 24 0 0 0 40 336L40 264A24 24 0 0 1 64 240L240 240" />
            <path d="M440 640L440 584A24 24 0 0 1 464 560L600 560" />
            <path d="M760 -40L760 16A24 24 0 0 0 784 40L880 40" />
            <path d="M1200 -40L1200 56A24 24 0 0 1 1176 80L1060 80" />
            <path d="M-40 440L56 440A24 24 0 0 1 80 464L80 520" />
            <path d="M1560 320L1560 224A24 24 0 0 0 1536 200L1440 200" />
            <path d="M240 640L240 560" />
            <path d="M1000 -40L1000 40" />
            <path d="M-40 200L40 200" />
        </g>

        <g class="wc-parts">
            <rect class="wc-ic" x="60" y="420" width="100" height="100" rx="4" />
            <path class="wc-pin" d="M60 440h-10M160 440h10M80 420v-10M80 520v10M60 460h-10M160 460h10M100 420v-10M100 520v10M60 480h-10M160 480h10M120 420v-10M120 520v10M60 500h-10M160 500h10M140 420v-10M140 520v10" />
            <circle class="wc-ic-notch" cx="72" cy="432" r="3" />
            <rect class="wc-ic" x="1380" y="60" width="116" height="72" rx="4" />
            <path class="wc-pin" d="M1380 74h-10M1496 74h10M1380 89h-10M1496 89h10M1380 103h-10M1496 103h10M1380 118h-10M1496 118h10" />
            <circle class="wc-ic-notch" cx="1392" cy="72" r="3" />
            <rect class="wc-ic" x="296" y="56" width="96" height="56" rx="4" />
            <path class="wc-pin" d="M296 70h-10M392 70h10M296 84h-10M392 84h10M296 98h-10M392 98h10" />
            <circle class="wc-ic-notch" cx="308" cy="68" r="3" />
            <rect class="wc-ic" x="1176" y="496" width="88" height="52" rx="4" />
            <path class="wc-pin" d="M1176 509h-10M1264 509h10M1176 522h-10M1264 522h10M1176 535h-10M1264 535h10" />
            <circle class="wc-ic-notch" cx="1188" cy="508" r="3" />
            <rect class="wc-ic" x="660" y="560" width="104" height="60" rx="4" />
            <path class="wc-pin" d="M660 572h-10M764 572h10M660 584h-10M764 584h10M660 596h-10M764 596h10M660 608h-10M764 608h10" />
            <circle class="wc-ic-notch" cx="672" cy="572" r="3" />
            <g class="wc-passive">
                <rect x="452" y="108" width="8" height="15" rx="2" />
                <rect x="466" y="108" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="1292" y="452" width="8" height="15" rx="2" />
                <rect x="1306" y="452" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="1024" y="552" width="15" height="8" rx="2" />
                <rect x="1024" y="566" width="15" height="8" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="52" y="172" width="8" height="15" rx="2" />
                <rect x="66" y="172" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="1508" y="176" width="15" height="8" rx="2" />
                <rect x="1508" y="190" width="15" height="8" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="868" y="152" width="8" height="15" rx="2" />
                <rect x="882" y="152" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="228" y="292" width="15" height="8" rx="2" />
                <rect x="228" y="306" width="15" height="8" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="1012" y="352" width="8" height="15" rx="2" />
                <rect x="1026" y="352" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="396" y="300" width="8" height="15" rx="2" />
                <rect x="410" y="300" width="8" height="15" rx="2" />
            </g>
            <g class="wc-passive">
                <rect x="1420" y="620" width="15" height="8" rx="2" />
                <rect x="1420" y="634" width="15" height="8" rx="2" />
            </g>
            <g class="wc-header">
                <rect x="1056" y="296" width="10" height="24" rx="2" />
                <rect x="1072" y="296" width="10" height="24" rx="2" />
                <rect x="1088" y="296" width="10" height="24" rx="2" />
                <rect x="1104" y="296" width="10" height="24" rx="2" />
                <rect x="1120" y="296" width="10" height="24" rx="2" />
                <rect x="1136" y="296" width="10" height="24" rx="2" />
                <rect x="1152" y="296" width="10" height="24" rx="2" />
                <rect x="1168" y="296" width="10" height="24" rx="2" />
            </g>
            <g class="wc-holes">
                <circle cx="1560" cy="40" r="13" />
                <circle class="wc-hole-core" cx="1560" cy="40" r="6" />
                <circle cx="40" cy="600" r="13" />
                <circle class="wc-hole-core" cx="40" cy="600" r="6" />
            </g>
            <g class="wc-testpoints">
                <circle cx="500" cy="600" r="4" />
                <circle cx="900" cy="20" r="4" />
                <circle cx="1360" cy="260" r="4" />
                <circle cx="180" cy="340" r="4" />
                <circle cx="740" cy="340" r="4" />
                <circle cx="1460" cy="560" r="4" />
                <circle cx="60" cy="120" r="4" />
                <circle cx="1240" cy="620" r="4" />
            </g>
        </g>

        <g class="wc-vias">
            <circle class="wc-pad" cx="280" cy="200" r="6" />
            <circle class="wc-pad-core" cx="280" cy="200" r="2.5" />
            <circle class="wc-pad" cx="760" cy="80" r="6" />
            <circle class="wc-pad-core" cx="760" cy="80" r="2.5" />
            <circle class="wc-pad" cx="920" cy="200" r="6" />
            <circle class="wc-pad-core" cx="920" cy="200" r="2.5" />
            <circle class="wc-pad" cx="200" cy="440" r="6" />
            <circle class="wc-pad-core" cx="200" cy="440" r="2.5" />
            <circle class="wc-pad" cx="680" cy="520" r="6" />
            <circle class="wc-pad-core" cx="680" cy="520" r="2.5" />
            <circle class="wc-pad" cx="120" cy="320" r="6" />
            <circle class="wc-pad-core" cx="120" cy="320" r="2.5" />
            <circle class="wc-pad" cx="560" cy="200" r="6" />
            <circle class="wc-pad-core" cx="560" cy="200" r="2.5" />
            <circle class="wc-pad" cx="640" cy="280" r="6" />
            <circle class="wc-pad-core" cx="640" cy="280" r="2.5" />
            <circle class="wc-pad" cx="840" cy="440" r="6" />
            <circle class="wc-pad-core" cx="840" cy="440" r="2.5" />
            <circle class="wc-pad" cx="1240" cy="240" r="6" />
            <circle class="wc-pad-core" cx="1240" cy="240" r="2.5" />
            <circle class="wc-pad" cx="1120" cy="320" r="6" />
            <circle class="wc-pad-core" cx="1120" cy="320" r="2.5" />
            <circle class="wc-pad" cx="320" cy="560" r="6" />
            <circle class="wc-pad-core" cx="320" cy="560" r="2.5" />
            <circle class="wc-pad" cx="800" cy="120" r="6" />
            <circle class="wc-pad-core" cx="800" cy="120" r="2.5" />
            <circle class="wc-pad" cx="200" cy="40" r="6" />
            <circle class="wc-pad-core" cx="200" cy="40" r="2.5" />
            <circle class="wc-pad" cx="1440" cy="400" r="6" />
            <circle class="wc-pad-core" cx="1440" cy="400" r="2.5" />
            <circle class="wc-pad" cx="1320" cy="120" r="6" />
            <circle class="wc-pad-core" cx="1320" cy="120" r="2.5" />
        </g>

        <g class="wc-traces">
            <use class="wc-trace" href="#wc-{{ $uid }}-t1" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t2" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t3" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t4" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t5" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t6" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t7" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t8" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t9" />
            <use class="wc-trace" href="#wc-{{ $uid }}-t10" />
        </g>

        <g class="wc-pulses">
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t1"
                style="--dur: 18s; --delay: 0s; --gap: 1810; --from: 146; --to: -1380"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t1"
                style="--dur: 18s; --delay: 0s; --gap: 1810; --from: 16; --to: -1510"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t2"
                style="--dur: 16s; --delay: 1.6s; --gap: 1901; --from: 146; --to: -1471"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t2"
                style="--dur: 16s; --delay: 1.6s; --gap: 1901; --from: 16; --to: -1601"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t3"
                style="--dur: 17s; --delay: 3.4s; --gap: 2156; --from: 146; --to: -1726"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t3"
                style="--dur: 17s; --delay: 3.4s; --gap: 2156; --from: 16; --to: -1856"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t4"
                style="--dur: 13s; --delay: 0.8s; --gap: 2591; --from: 146; --to: -2161"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t4"
                style="--dur: 13s; --delay: 0.8s; --gap: 2591; --from: 16; --to: -2291"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t5"
                style="--dur: 14s; --delay: 4.5s; --gap: 2685; --from: 146; --to: -2255"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t5"
                style="--dur: 14s; --delay: 4.5s; --gap: 2685; --from: 16; --to: -2385"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t6"
                style="--dur: 15s; --delay: 2.4s; --gap: 2174; --from: 146; --to: -1744"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t6"
                style="--dur: 15s; --delay: 2.4s; --gap: 2174; --from: 16; --to: -1874"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t7"
                style="--dur: 12s; --delay: 5.6s; --gap: 2967; --from: 146; --to: -2537"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t7"
                style="--dur: 12s; --delay: 5.6s; --gap: 2967; --from: 16; --to: -2667"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t8"
                style="--dur: 11s; --delay: 2.9s; --gap: 3815; --from: 146; --to: -3385"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t8"
                style="--dur: 11s; --delay: 2.9s; --gap: 3815; --from: 16; --to: -3515"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t9"
                style="--dur: 19s; --delay: 6.6s; --gap: 7461; --from: 146; --to: -7031"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t9"
                style="--dur: 19s; --delay: 6.6s; --gap: 7461; --from: 16; --to: -7161"
            />
            <use
                class="wc-tail"
                href="#wc-{{ $uid }}-t10"
                style="--dur: 14s; --delay: 4s; --gap: 6937; --from: 146; --to: -6507"
            />
            <use
                class="wc-pulse"
                href="#wc-{{ $uid }}-t10"
                style="--dur: 14s; --delay: 4s; --gap: 6937; --from: 16; --to: -6637"
            />
        </g>

        <g class="wc-terminals">
            <g class="wc-term" style="--dur: 18s; --arrive: 11.89s; --breathe: 9s; --breathe-delay: -0s">
                <circle class="wc-term-glow" cx="1240" cy="320" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1240" cy="320" r="5.5" />
                <circle class="wc-term-flare" cx="1240" cy="320" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 16s; --arrive: 11.57s; --breathe: 11s; --breathe-delay: -1.7s">
                <circle class="wc-term-glow" cx="1200" cy="440" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1200" cy="440" r="5.5" />
                <circle class="wc-term-flare" cx="1200" cy="440" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 17s; --arrive: 12.55s; --breathe: 9.5s; --breathe-delay: -3.3s">
                <circle class="wc-term-glow" cx="1040" cy="280" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1040" cy="280" r="5.5" />
                <circle class="wc-term-flare" cx="1040" cy="280" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 13s; --arrive: 6.48s; --breathe: 12s; --breathe-delay: -0.8s">
                <circle class="wc-term-glow" cx="1000" cy="440" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1000" cy="440" r="5.5" />
                <circle class="wc-term-flare" cx="1000" cy="440" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 14s; --arrive: 10.38s; --breathe: 8.5s; --breathe-delay: -4.3s">
                <circle class="wc-term-glow" cx="1320" cy="400" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1320" cy="400" r="5.5" />
                <circle class="wc-term-flare" cx="1320" cy="400" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 15s; --arrive: 10.4s; --breathe: 11.5s; --breathe-delay: -2.5s">
                <circle class="wc-term-glow" cx="1000" cy="120" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1000" cy="120" r="5.5" />
                <circle class="wc-term-flare" cx="1000" cy="120" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 12s; --arrive: 10.11s; --breathe: 10s; --breathe-delay: -5.8s">
                <circle class="wc-term-glow" cx="520" cy="160" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="520" cy="160" r="5.5" />
                <circle class="wc-term-flare" cx="520" cy="160" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 11s; --arrive: 6.04s; --breathe: 9.2s; --breathe-delay: -4s">
                <circle class="wc-term-glow" cx="1280" cy="360" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1280" cy="360" r="5.5" />
                <circle class="wc-term-flare" cx="1280" cy="360" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 19s; --arrive: 9.27s; --breathe: 13s; --breathe-delay: -0.4s">
                <circle class="wc-term-glow" cx="1320" cy="160" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="1320" cy="160" r="5.5" />
                <circle class="wc-term-flare" cx="1320" cy="160" r="5.5" />
            </g>
            <g class="wc-term" style="--dur: 14s; --arrive: 6.12s; --breathe: 10.5s; --breathe-delay: -6.9s">
                <circle class="wc-term-glow" cx="200" cy="520" r="30" fill="url(#wc-bloom-{{ $uid }})" />
                <circle class="wc-term-core" cx="200" cy="520" r="5.5" />
                <circle class="wc-term-flare" cx="200" cy="520" r="5.5" />
            </g>
        </g>
    </svg>
</div>
