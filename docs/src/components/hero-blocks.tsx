"use client";

import * as THREE from "three";
import { useRef, useState } from "react";
import { Canvas, useFrame } from "@react-three/fiber";
import {
  Environment,
  Lightformer,
  MeshTransmissionMaterial,
  RoundedBox,
} from "@react-three/drei";

function Block() {
  const meshRef = useRef<THREE.Mesh>(null);

  useFrame((_, delta) => {
    if (!meshRef.current) return;
    meshRef.current.rotation.x += delta * 0.08;
    meshRef.current.rotation.y += delta * 0.12;
  });

  return (
    <RoundedBox
      ref={meshRef}
      args={[1.5, 1.5, 1.5]}
      radius={0}
      rotation={[-0.5, 0.7, 0.15]}
    >
      <MeshTransmissionMaterial
        backside
        samples={16}
        resolution={1024}
        thickness={2}
        chromaticAberration={0.3}
        anisotropy={0.5}
        distortion={0.4}
        distortionScale={0.5}
        temporalDistortion={0.2}
        roughness={0.05}
        ior={1.5}
        color="#ffffff"
        attenuationColor="#ede9fe"
        attenuationDistance={1.2}
        envMapIntensity={2.5}
        transmissionSampler={false}
      />
    </RoundedBox>
  );
}

function ReadySignal({ onReady }: { onReady: () => void }) {
  const frames = useRef(0);
  useFrame(() => {
    frames.current++;
    if (frames.current === 10) {
      onReady();
    }
  });
  return null;
}

function Scene({ onReady }: { onReady: () => void }) {
  return (
    <>
      <Block />
      <ReadySignal onReady={onReady} />
      <Environment resolution={512}>
        <Lightformer form="rect" intensity={0.8} position={[0, 0, -5]} scale={20} />
        <Lightformer form="rect" intensity={0.8} position={[0, 0, 5]} scale={20} />
        <Lightformer form="circle" intensity={15} position={[5, 5, -5]} scale={3} color="#e879f9" />
        <Lightformer form="circle" intensity={15} position={[-5, -3, 5]} scale={3} color="#7dd3fc" />
        <Lightformer form="circle" intensity={10} position={[-5, 5, 0]} scale={4} color="#818cf8" />
        <Lightformer form="circle" intensity={10} position={[5, -5, 0]} scale={4} color="#f0abfc" />
        <Lightformer form="ring" intensity={8} position={[0, 8, 0]} scale={8} color="#c4b5fd" />
        <Lightformer form="ring" intensity={6} position={[0, -8, 0]} scale={8} color="#38bdf8" />
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
        minHeight: 300,
        maxHeight: 300,
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
