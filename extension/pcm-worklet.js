// Audio thread: downmix to mono, box-filter downsample to 16 kHz, 16-bit PCM, post 50 ms chunks (800 samples).
class Pcm16 extends AudioWorkletProcessor {
  constructor() { super(); this.ratio = sampleRate / 16000; this.t = 0; this.acc = 0; this.cnt = 0; this.out = new Int16Array(800); this.n = 0; }
  process(inputs) {
    const ch = inputs[0] && inputs[0][0];
    if (!ch) return true;
    for (let i = 0; i < ch.length; i++) {
      this.acc += ch[i]; this.cnt++; this.t += 1;
      if (this.t >= this.ratio) {
        this.t -= this.ratio;
        const s = Math.max(-1, Math.min(1, this.acc / this.cnt));
        this.acc = 0; this.cnt = 0;
        this.out[this.n++] = s < 0 ? s * 0x8000 : s * 0x7fff;
        if (this.n === this.out.length) { this.port.postMessage(this.out.buffer, [this.out.buffer]); this.out = new Int16Array(800); this.n = 0; }
      }
    }
    return true;
  }
}
registerProcessor("pcm16", Pcm16);
