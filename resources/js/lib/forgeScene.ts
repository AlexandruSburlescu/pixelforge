import {
    AdditiveBlending,
    BufferGeometry,
    Color,
    Float32BufferAttribute,
    PerspectiveCamera,
    Points,
    Scene,
    ShaderMaterial,
    WebGLRenderer,
} from 'three';

/**
 * "Pixel forge": a cloud of square pixels drifts in, locks into a picture grid
 * (the image being forged), holds, and scatters again. One draw call (Points +
 * a small shader), DPR capped, paused when off-screen or the tab is hidden.
 * Loaded lazily after first paint; returns a cleanup function.
 */
export function mount(canvas: HTMLCanvasElement, accent: string): () => void {
    const renderer = new WebGLRenderer({ canvas, antialias: false, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));

    const scene = new Scene();
    const camera = new PerspectiveCamera(40, 1, 0.1, 100);
    camera.position.set(0, 0, 9);

    const SIDE = 64; // 64×64 = 4096 pixels
    const count = SIDE * SIDE;
    const grid = new Float32Array(count * 3);
    const cloud = new Float32Array(count * 3);
    const seed = new Float32Array(count);
    const shade = new Float32Array(count);

    for (let i = 0; i < count; i++) {
        const x = i % SIDE;
        const y = Math.floor(i / SIDE);
        const u = x / (SIDE - 1) - 0.5;
        const v = y / (SIDE - 1) - 0.5;

        grid.set([u * 5, v * 5, 0], i * 3);

        // Scattered start: a loose sphere shell.
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 3.5 + Math.random() * 2.5;
        cloud.set([r * Math.sin(phi) * Math.cos(theta), r * Math.sin(phi) * Math.sin(theta), r * Math.cos(phi) - 1], i * 3);

        seed[i] = Math.random();
        // The "image": a soft sun over a horizon, in brightness only.
        const sun = Math.max(0, 1 - Math.hypot(u, v - 0.12) * 3.2);
        const horizon = v < -0.08 ? 0.25 + 0.2 * Math.sin(u * 24 + v * 10) : 0.08;
        shade[i] = Math.min(1, sun + horizon);
    }

    const geometry = new BufferGeometry();
    geometry.setAttribute('position', new Float32BufferAttribute(grid, 3));
    geometry.setAttribute('aCloud', new Float32BufferAttribute(cloud, 3));
    geometry.setAttribute('aSeed', new Float32BufferAttribute(seed, 1));
    geometry.setAttribute('aShade', new Float32BufferAttribute(shade, 1));

    const material = new ShaderMaterial({
        transparent: true,
        depthWrite: false,
        blending: AdditiveBlending,
        uniforms: {
            uTime: { value: 0 },
            uSize: { value: 4.0 * renderer.getPixelRatio() },
            uDepth: { value: 9.0 },
            uAccent: { value: new Color(accent) },
        },
        vertexShader: /* glsl */ `
            uniform float uTime;
            uniform float uSize;
            uniform float uDepth;
            attribute vec3 aCloud;
            attribute float aSeed;
            attribute float aShade;
            varying float vShade;
            varying float vForm;
            void main() {
                // 12s loop: drift in, hold the picture, scatter out. Each pixel has its own delay.
                float t = mod(uTime / 12.0, 1.0);
                float form = smoothstep(0.05 + aSeed * 0.25, 0.35 + aSeed * 0.2, t)
                           * (1.0 - smoothstep(0.78 + aSeed * 0.1, 0.98, t));
                vec3 drift = aCloud + 0.15 * vec3(sin(uTime * 0.6 + aSeed * 40.0), cos(uTime * 0.5 + aSeed * 30.0), 0.0);
                vec3 p = mix(drift, position, form);
                vShade = aShade;
                vForm = form;
                vec4 mv = modelViewMatrix * vec4(p, 1.0);
                // uSize is the cell size at the grid's depth; perspective scales the rest.
                gl_PointSize = uSize * (uDepth / -mv.z);
                gl_Position = projectionMatrix * mv;
            }
        `,
        fragmentShader: /* glsl */ `
            uniform vec3 uAccent;
            varying float vShade;
            varying float vForm;
            void main() {
                // Square sprites on purpose: they're pixels.
                vec3 white = vec3(0.92);
                vec3 color = mix(white * 0.35, mix(uAccent, white, vShade * 0.6), vForm);
                float alpha = mix(0.25, 0.25 + vShade * 0.75, vForm);
                gl_FragColor = vec4(color, alpha);
            }
        `,
    });

    const points = new Points(geometry, material);
    scene.add(points);

    let pointerX = 0;
    let pointerY = 0;
    const onPointer = (e: PointerEvent) => {
        pointerX = (e.clientX / window.innerWidth - 0.5) * 2;
        pointerY = (e.clientY / window.innerHeight - 0.5) * 2;
    };

    const resize = () => {
        const { clientWidth: w, clientHeight: h } = canvas;
        if (w === 0 || h === 0) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        // Fit the picture to the frame whatever its shape.
        camera.position.z = camera.aspect < 1 ? 9 / camera.aspect : 9;

        // Size each pixel from the on-screen grid cell so they never overlap
        // (overlap reads as a plaid pattern on small frames).
        const visibleHeight = 2 * camera.position.z * Math.tan((camera.fov * Math.PI) / 360);
        const cellPx = ((h / visibleHeight) * 5) / SIDE;
        material.uniforms.uSize.value = cellPx * 0.78 * renderer.getPixelRatio();
        material.uniforms.uDepth.value = camera.position.z;
    };

    let frame = 0;
    let visible = true;
    const start = performance.now();

    const render = () => {
        frame = 0;
        if (!visible || document.hidden) return;

        material.uniforms.uTime.value = (performance.now() - start) / 1000;
        points.rotation.y += (pointerX * 0.35 - points.rotation.y) * 0.04;
        points.rotation.x += (pointerY * 0.2 - points.rotation.x) * 0.04;
        renderer.render(scene, camera);
        frame = requestAnimationFrame(render);
    };

    const play = () => {
        if (!frame) frame = requestAnimationFrame(render);
    };

    const observer = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) play();
    });
    observer.observe(canvas);

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(canvas);
    window.addEventListener('pointermove', onPointer, { passive: true });
    document.addEventListener('visibilitychange', play);

    resize();
    play();

    return () => {
        cancelAnimationFrame(frame);
        observer.disconnect();
        resizeObserver.disconnect();
        window.removeEventListener('pointermove', onPointer);
        document.removeEventListener('visibilitychange', play);
        geometry.dispose();
        material.dispose();
        renderer.dispose();
    };
}
