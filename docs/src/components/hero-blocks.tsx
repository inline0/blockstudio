"use client";

import * as THREE from "three";
import { useRef, useState } from "react";
import { Canvas, useFrame } from "@react-three/fiber";
import {
  Environment,
  Lightformer,
  MeshTransmissionMaterial,
} from "@react-three/drei";

function InnerCore() {
  const ref = useRef<THREE.Mesh>(null);

  useFrame(({ clock }) => {
    if (!ref.current) return;
    const t = clock.getElapsedTime();
    ref.current.rotation.x = t * 0.3;
    ref.current.rotation.z = t * 0.2;
    const s = 0.45 + Math.sin(t * 0.8) * 0.05;
    ref.current.scale.setScalar(s);
  });

  return (
    <mesh ref={ref}>
      <boxGeometry args={[1, 1, 1]} />
      <meshStandardMaterial
        color="#8b7ba8"
        emissive="#6d5a8a"
        emissiveIntensity={1.5}
        metalness={0.9}
        roughness={0.1}
      />
    </mesh>
  );
}

function Block() {
  const meshRef = useRef<THREE.Group>(null);

  useFrame((_, delta) => {
    if (!meshRef.current) return;
    meshRef.current.rotation.x += delta * 0.08;
    meshRef.current.rotation.y += delta * 0.12;
  });

  return (
    <group ref={meshRef} rotation={[0.4, 0.7, 0.1]}>
      <mesh>
        <boxGeometry args={[1.5, 1.5, 1.5]} />
        <MeshTransmissionMaterial
          backside
          samples={32}
          resolution={2048}
          thickness={2.5}
          chromaticAberration={0.15}
          anisotropy={0.3}
          distortion={0.1}
          distortionScale={0.2}
          temporalDistortion={0.05}
          roughness={0.02}
          ior={1.3}
          color="#ffffff"
          attenuationColor="#e9d5ff"
          attenuationDistance={1.5}
          envMapIntensity={0.8}
          transmissionSampler={false}
        />
      </mesh>
      <InnerCore />
    </group>
  );
}

function ReadySignal({ onReady }: { onReady: () => void }) {
  const frames = useRef(0);
  useFrame(() => {
    frames.current++;
    if (frames.current === 30) {
      onReady();
    }
  });
  return null;
}

function AnimatedLights() {
  const groupRef = useRef<THREE.Group>(null);

  useFrame(({ clock }) => {
    if (!groupRef.current) return;
    groupRef.current.rotation.y = clock.getElapsedTime() * 0.15;
    groupRef.current.rotation.x = Math.sin(clock.getElapsedTime() * 0.1) * 0.3;
  });

  return (
    <group ref={groupRef}>
      <Lightformer form="circle" intensity={12} position={[6, 4, -4]} scale={3} color="#c9a0c9" />
      <Lightformer form="circle" intensity={12} position={[-6, -2, 4]} scale={3} color="#8ba0b8" />
      <Lightformer form="circle" intensity={8} position={[-4, 6, 2]} scale={3} color="#9a8ab8" />
      <Lightformer form="circle" intensity={8} position={[4, -6, -2]} scale={3} color="#b89aaa" />
    </group>
  );
}

function Scene({ onReady }: { onReady: () => void }) {
  return (
    <>
      <Block />
      <ReadySignal onReady={onReady} />
      <Environment resolution={512}>
        <Lightformer form="rect" intensity={1} position={[0, 0, -5]} scale={20} />
        <Lightformer form="rect" intensity={1} position={[0, 0, 5]} scale={20} />
        <Lightformer form="ring" intensity={6} position={[0, 8, 0]} scale={10} color="#b0a0c0" />
        <Lightformer form="ring" intensity={5} position={[0, -8, 0]} scale={10} color="#8a9aaa" />
        <AnimatedLights />
      </Environment>
    </>
  );
}

export function HeroBlocks() {
  const [ready, setReady] = useState(false);

  return (
    <div
      className="h-full w-full transition-all duration-1000 ease-out"
      style={{
        minHeight: 270,
        maxHeight: 270,
        opacity: ready ? 1 : 0,
        filter: ready ? "blur(0px)" : "blur(12px)",
      }}
    >
      <Canvas
        dpr={[1, 2]}
        gl={{
          antialias: true,
          alpha: true,
          powerPreference: "high-performance",
          toneMapping: THREE.ACESFilmicToneMapping,
          toneMappingExposure: 1.0,
        }}
        camera={{ position: [0, 0, 5], fov: 35, near: 0.1, far: 100 }}
        style={{ background: "transparent" }}
      >
        <Scene onReady={() => setReady(true)} />
      </Canvas>
    </div>
  );
}
