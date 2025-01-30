import { useEffect, useRef } from "react";

interface AudioMeterProps {
  stream: MediaStream | null;
  onVolumeChange?: (volume: number) => void;
}

export default function AudioMeter({
  stream,
  onVolumeChange,
}: AudioMeterProps) {
  const audioContextRef = useRef<AudioContext | null>(null);
  const analyserRef = useRef<AnalyserNode | null>(null);
  const dataArrayRef = useRef<Uint8Array | null>(null);
  const rafIdRef = useRef<number>();

  useEffect(() => {
    if (!stream) return;

    audioContextRef.current = new AudioContext();
    analyserRef.current = audioContextRef.current.createAnalyser();
    const source = audioContextRef.current.createMediaStreamSource(stream);
    source.connect(analyserRef.current);
    analyserRef.current.fftSize = 256;

    const bufferLength = analyserRef.current.frequencyBinCount;
    dataArrayRef.current = new Uint8Array(bufferLength);

    const updateMeter = () => {
      if (!analyserRef.current || !dataArrayRef.current) return;

      analyserRef.current.getByteFrequencyData(dataArrayRef.current);
      const average =
        dataArrayRef.current.reduce((a, b) => a + b) / bufferLength;
      const volume = Math.min(100, Math.round((average / 255) * 100));

      onVolumeChange?.(volume);
      rafIdRef.current = requestAnimationFrame(updateMeter);
    };

    updateMeter();

    return () => {
      if (rafIdRef.current) {
        cancelAnimationFrame(rafIdRef.current);
      }
      audioContextRef.current?.close();
    };
  }, [stream]);

  return null;
}
