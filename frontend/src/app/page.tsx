"use client";

import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import { useGSAP } from "@gsap/react";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "@studio-freight/lenis";
import { Activity, Beaker, Users, ChevronRight, ActivitySquare, BrainCircuit, ScanLine, ShieldCheck, Search, Bell, Menu, LayoutDashboard, Database, Scale, Settings, Terminal, Cpu, Fingerprint, Lock, Globe } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

if (typeof window !== "undefined") {
  gsap.registerPlugin(useGSAP, ScrollTrigger);
}

const TechnicalMarker = ({ label, position }: { label: string, position: string }) => (
  <div className={`coordinate-marker ${position} hidden md:block`}>
    [{label}]
  </div>
);

const CornerBrackets = ({ position }: { position: string }) => (
  <div className={`absolute ${position} w-4 h-4 border-em-900/10 pointer-events-none`}>
    {position.includes("top-0 left-0") && <div className="absolute top-0 left-0 w-full h-full border-t border-l" />}
    {position.includes("top-0 right-0") && <div className="absolute top-0 right-0 w-full h-full border-t border-r" />}
    {position.includes("bottom-0 left-0") && <div className="absolute bottom-0 left-0 w-full h-full border-b border-l" />}
    {position.includes("bottom-0 right-0") && <div className="absolute bottom-0 right-0 w-full h-full border-b border-r" />}
  </div>
);

export default function NutriDeqLanding() {
  const container = useRef<HTMLDivElement>(null);
  const [splashFinished, setSplashFinished] = useState(false);
  const [isHovering, setIsHovering] = useState(false);
  const [equityMode, setEquityMode] = useState("standard");
  const [tickerValue, setTickerValue] = useState(104859);
  const [systemTime, setSystemTime] = useState("");

  // Smooth Scroll
  useEffect(() => {
    const lenis = new Lenis({
      duration: 1.2,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      orientation: "vertical",
      gestureOrientation: "vertical",
      smoothWheel: true,
    });

    function raf(time: number) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    return () => {
      lenis.destroy();
    };
  }, []);

  // System Time Ticker
  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      setSystemTime(now.toLocaleTimeString('en-US', { hour12: false }) + ":" + now.getMilliseconds());
    };
    const timer = setInterval(updateTime, 100);
    return () => clearInterval(timer);
  }, []);

  // Cursor Tracking
  useEffect(() => {
    const cursor = document.querySelector(".custom-cursor") as HTMLElement;
    if (!cursor) return;
    
    const xTo = gsap.quickTo(cursor, "x", { duration: 0.1, ease: "none" });
    const yTo = gsap.quickTo(cursor, "y", { duration: 0.1, ease: "none" });

    const handleMouseMove = (e: MouseEvent) => {
      xTo(e.clientX);
      yTo(e.clientY);
    };
    window.addEventListener("mousemove", handleMouseMove);
    return () => window.removeEventListener("mousemove", handleMouseMove);
  }, []);

  // Ticker Logic
  useEffect(() => {
    const interval = setInterval(() => {
      setTickerValue(prev => prev + Math.floor(Math.random() * 5));
    }, 2000);
    return () => clearInterval(interval);
  }, []);

  // GSAP Animations
  useGSAP(() => {
    if (!splashFinished) return;

    // Hero 2.5D Parallax
    const heroCard = document.querySelector(".hero-dashboard");
    if (heroCard) {
      window.addEventListener("mousemove", (e) => {
        const xAxis = (window.innerWidth / 2 - e.pageX) / 80;
        const yAxis = (window.innerHeight / 2 - e.pageY) / 80;
        gsap.to(heroCard, {
          rotateY: xAxis,
          rotateX: yAxis,
          duration: 2,
          ease: "power2.out",
        });
      });
    }

    // Technical Debris Float
    gsap.to(".debris-float", {
      y: -20,
      repeat: -1,
      yoyo: true,
      duration: 3,
      ease: "sine.inOut"
    });

  }, { scope: container, dependencies: [splashFinished] });

  // Handle Splash Screen
  useEffect(() => {
    const timer = setTimeout(() => {
      setSplashFinished(true);
    }, 2000);
    return () => clearTimeout(timer);
  }, []);

  const handleMouseEnter = () => setIsHovering(true);
  const handleMouseLeave = () => setIsHovering(false);

  return (
    <div ref={container} className="relative w-full overflow-hidden text-em-950 font-sans selection:bg-em-950 selection:text-white" style={{ perspective: "2000px" }}>
      
      {/* Texture & Tech Layers */}
      <div className="grain-overlay" />
      <div className="page-bg" />
      <div className="technical-grid" />

      {/* Background Data Stream (Moving Lines) */}
      <div className="fixed inset-0 pointer-events-none opacity-[0.03] z-[-1]">
        <svg width="100%" height="100%">
          <pattern id="data-lines" x="0" y="0" width="100" height="100" patternUnits="userSpaceOnUse">
             <motion.line 
               initial={{ x1: 0, x2: 100 }}
               animate={{ y1: [0, 100], y2: [0, 100] }}
               transition={{ duration: 10, repeat: Infinity, ease: "linear" }}
               stroke="currentColor" strokeWidth="0.5" 
             />
          </pattern>
          <rect width="100%" height="100%" fill="url(#data-lines)" />
        </svg>
      </div>

      {/* Custom Cursor */}
      <div className={`custom-cursor ${isHovering ? "hovering" : ""}`} />

      {/* Splash Screen */}
      <AnimatePresence>
        {!splashFinished && (
          <motion.div 
            className="splash-screen flex-col !bg-offwhite"
            exit={{ opacity: 0, scale: 1.1, filter: "blur(50px)" }}
            transition={{ duration: 0.8, ease: "easeInOut" }}
          >
            <div className="flex flex-col items-center gap-6">
              <div className="w-16 h-16 bg-em-950 flex items-center justify-center relative shadow-2xl">
                 <Lock className="w-6 h-6 text-white" />
                 <div className="absolute inset-0 border border-white/20 animate-pulse" />
              </div>
              <div className="flex flex-col items-center">
                 <span className="text-[10px] font-black uppercase tracking-[0.5em] text-em-950 mb-2">Establishing Secure Link</span>
                 <div className="w-48 h-[1px] bg-em-950/5 relative overflow-hidden">
                    <motion.div 
                      initial={{ left: "-100%" }}
                      animate={{ left: "100%" }}
                      transition={{ duration: 1, repeat: Infinity }}
                      className="absolute inset-0 bg-em-950 w-1/3"
                    />
                 </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Industrial Header */}
      <header className="fixed top-0 inset-x-0 z-50 h-24 border-b border-em-950/5 bg-offwhite/80 backdrop-blur-xl flex items-center justify-between px-12">
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 bg-em-950 flex items-center justify-center relative">
            <Activity className="w-5 h-5 text-white" />
            <div className="absolute -bottom-1 -right-1 w-3 h-3 bg-em-600" />
          </div>
          <div className="flex flex-col">
            <span className="font-black text-2xl tracking-tighter uppercase leading-none">Nutri<span className="text-em-600">Deq</span></span>
            <span className="text-[8px] font-black text-em-950/30 uppercase tracking-widest mt-1">Clinical Control Node</span>
          </div>
        </div>
        
        <div className="hidden lg:flex items-center gap-16 text-[10px] font-black uppercase tracking-[0.3em] text-em-950/40">
          {["Intelligence", "Protocols", "Equity", "Registry"].map((item) => (
            <a key={item} href="#" className="hover:text-em-950 transition-colors relative group">
              {item}
              <span className="absolute -bottom-2 left-0 w-0 h-[2px] bg-em-600 transition-all group-hover:w-full" />
            </a>
          ))}
        </div>

        <div className="flex items-center gap-10">
          <div className="hidden xl:flex flex-col items-end">
            <span className="text-[8px] font-black text-em-950/30 uppercase tracking-widest">Global Timestamp</span>
            <span className="text-[10px] font-black text-em-950 tabular-nums">{systemTime}</span>
          </div>
          <button className="bg-em-950 text-white px-10 py-4 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-em-800 transition-all flex items-center gap-3">
             Access Portal <ChevronRight className="w-3 h-3" />
          </button>
        </div>
      </header>

      <main className="relative z-10">
        
        {/* HERO SECTION */}
        <section id="hero" className="min-h-screen flex flex-col items-center justify-center pt-48 pb-32 px-12 relative">
          <TechnicalMarker label="REF: 40.2.1" position="top-40 left-12" />
          <TechnicalMarker label="SYS: STABLE" position="top-40 right-12" />
          
          <motion.div 
            initial={{ opacity: 0, y: 40 }}
            animate={splashFinished ? { opacity: 1, y: 0 } : {}}
            className="text-center max-w-[90rem] mx-auto mb-24 relative"
          >
            <div className="section-chip mx-auto mb-12">
               <Globe className="w-3 h-3" />
               Unified Clinical Standard v2026.04
            </div>
            <h1 className="text-8xl md:text-[14rem] font-black tracking-tighter text-em-950 mb-4 leading-[0.8] uppercase">
              Precision <br />
              <span className="gradient-text">Nutritional</span> <br />
              <span className="text-em-950/5 outline-text">Quantified</span>
            </h1>
            
            <div className="w-full flex flex-col md:flex-row items-center justify-center gap-16 mt-16 px-12">
               <div className="flex flex-col gap-4 max-w-sm text-left">
                  <span className="text-[10px] font-black text-em-600 uppercase tracking-[0.2em]">Data Integrity</span>
                  <p className="text-sm font-bold text-em-950/40 uppercase tracking-widest leading-relaxed">
                    Orchestrating surgical-grade dietary telemetry through sub-milligram quantification and autonomous exchange protocols.
                  </p>
               </div>
               <div className="h-[1px] flex-1 bg-em-950/5 hidden md:block" />
               <div className="flex flex-col gap-4 max-w-sm text-right">
                  <span className="text-[10px] font-black text-em-600 uppercase tracking-[0.2em]">Operational Equity</span>
                  <p className="text-sm font-bold text-em-950/40 uppercase tracking-widest leading-relaxed">
                    Bridging the clinical-cultural divide through adaptive algorithmic logic and resource-optimized dietary prescriptions.
                  </p>
               </div>
            </div>
          </motion.div>

          {/* Hero Dashboard - Ultra Tech */}
          <div className="w-full max-w-[100rem] mx-auto perspective-[3000px]">
            <div className="hero-dashboard w-full glass-panel-deep rounded-none p-6 flex flex-col gap-6 transform-style-3d shadow-[0_60px_120px_-30px_rgba(2,26,20,0.15)] border border-em-950/5 relative">
               <CornerBrackets position="top-0 left-0" />
               <CornerBrackets position="top-0 right-0" />
               <CornerBrackets position="bottom-0 left-0" />
               <CornerBrackets position="bottom-0 right-0" />
               
               {/* Terminal Top Bar */}
               <div className="w-full h-14 glass-panel flex items-center justify-between px-8 border-b border-em-950/10">
                  <div className="flex items-center gap-8">
                     <div className="flex gap-2">
                        <div className="w-3 h-3 bg-em-950/10" />
                        <div className="w-3 h-3 bg-em-950/10" />
                        <div className="w-3 h-3 bg-em-950/10" />
                     </div>
                     <span className="text-[10px] font-black uppercase tracking-[0.4em] text-em-950/30">Protocol_Matrix_Viewer.sys</span>
                  </div>
                  <div className="flex items-center gap-8 text-[9px] font-black text-em-950/40">
                     <span className="animate-pulse">SECURE_LINK_ACTIVE</span>
                     <div className="h-4 w-[1px] bg-em-950/10" />
                     <Terminal className="w-4 h-4" />
                  </div>
               </div>

               <div className="flex gap-6 h-[700px]">
                 {/* Technical Sidebar */}
                 <div className="w-80 glass-emerald p-10 flex flex-col gap-12 relative overflow-hidden">
                    <div className="absolute top-0 left-0 w-full h-1 bg-em-600/30" />
                    
                    <div className="flex flex-col gap-2">
                       <span className="text-[10px] font-black uppercase tracking-[0.4em] text-white/20">System Navigation</span>
                       <div className="w-12 h-1 bg-em-600" />
                    </div>

                    {[
                      { icon: LayoutDashboard, label: "Core Matrix", active: true },
                      { icon: Database, label: "Registry DB" },
                      { icon: ActivitySquare, label: "Telemetry" },
                      { icon: Scale, label: "Protocols" },
                      { icon: Users, label: "Collaborative" }
                    ].map((item, i) => (
                      <div key={i} className={`flex items-center gap-6 group cursor-pointer ${item.active ? 'text-white' : 'text-white/30 hover:text-white/60'}`}>
                         <item.icon className="w-5 h-5 transition-transform group-hover:scale-110" />
                         <span className="text-[11px] font-black uppercase tracking-[0.3em]">{item.label}</span>
                      </div>
                    ))}

                    <div className="mt-auto flex flex-col gap-6 pt-10 border-t border-white/5">
                       <div className="flex justify-between items-center">
                          <span className="text-[9px] font-black text-white/20 uppercase tracking-widest">Process Load</span>
                          <span className="text-[9px] font-black text-em-400 uppercase tracking-widest">14.2%</span>
                       </div>
                       <div className="flex gap-2 h-1">
                          {[...Array(10)].map((_, i) => (
                            <div key={i} className={`flex-1 ${i < 2 ? 'bg-em-400' : 'bg-white/5'}`} />
                          ))}
                       </div>
                    </div>
                 </div>

                 {/* Main Content Area */}
                 <div className="flex-1 flex flex-col gap-6">
                    <div className="grid grid-cols-3 gap-6">
                       {[
                         { label: "Biometric Drift", val: "±0.002", unit: "Δ", status: "STABLE" },
                         { label: "Exchange Latency", val: "14", unit: "MS", status: "OPTIMIZED" },
                         { label: "Data Integrity", val: "99.9", unit: "%", status: "LOCKED" }
                       ].map((stat, i) => (
                         <div key={i} className="glass-panel p-10 flex flex-col gap-6 border-b-4 border-em-950">
                            <div className="flex justify-between items-center">
                               <span className="text-[10px] font-black uppercase tracking-[0.3em] text-em-950/40">{stat.label}</span>
                               <div className="w-3 h-3 border border-em-950/10 flex items-center justify-center">
                                  <div className="w-1 h-1 bg-em-950/20" />
                               </div>
                            </div>
                            <div className="flex items-baseline gap-2">
                               <div className="text-5xl font-black text-em-950 tabular-nums">{stat.val}</div>
                               <span className="text-sm font-black text-em-950/20">{stat.unit}</span>
                            </div>
                            <div className="flex items-center gap-3">
                               <span className="text-[9px] font-black text-em-600 tracking-[0.3em]">{stat.status}</span>
                               <div className="flex-1 h-[1px] bg-em-950/5" />
                            </div>
                         </div>
                       ))}
                    </div>

                    <div className="flex-1 glass-panel p-12 flex flex-col gap-10 relative overflow-hidden">
                       <div className="absolute inset-0 opacity-[0.02] pointer-events-none">
                          <svg width="100%" height="100%">
                            <pattern id="grid-inner" width="40" height="40" patternUnits="userSpaceOnUse">
                               <circle cx="20" cy="20" r="1" fill="currentColor" />
                            </pattern>
                            <rect width="100%" height="100%" fill="url(#grid-inner)" />
                          </svg>
                       </div>
                       
                       <div className="flex justify-between items-end relative z-10">
                          <div className="flex flex-col gap-2">
                             <span className="text-[11px] font-black uppercase tracking-[0.4em] text-em-950">Registry_Telemetry_Stream</span>
                             <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-em-600 animate-pulse" />
                                <span className="text-[9px] font-black uppercase tracking-[0.3em] text-em-950/30">Channel_A4 :: Node_Active</span>
                             </div>
                          </div>
                          <div className="flex gap-4">
                             {["1H", "24H", "7D"].map(t => (
                               <button key={t} className={`px-4 py-1.5 text-[9px] font-black uppercase tracking-widest ${t === "24H" ? 'bg-em-950 text-white' : 'bg-em-50 text-em-950/40 hover:bg-em-100 transition-colors'}`}>
                                 {t}
                               </button>
                             ))}
                          </div>
                       </div>

                       <div className="flex-1 flex items-end gap-4 relative z-10">
                          {[30, 50, 45, 80, 60, 40, 95, 70, 50, 85, 30, 65, 45, 90, 55, 75, 40, 80, 60, 95].map((h, i) => (
                            <div key={i} className="flex-1 bar-chart-bar" style={{ height: `${h}%` }} />
                          ))}
                       </div>
                    </div>
                 </div>
               </div>
            </div>
          </div>
        </section>

        {/* BENTO GRID - TECHNICAL EDITION */}
        <section className="py-48 px-12 relative">
          <TechnicalMarker label="MODULE_B" position="top-24 right-24" />
          <div className="max-w-[100rem] mx-auto">
            <div className="flex flex-col items-center mb-32">
               <div className="section-chip mb-10">
                 <Cpu className="w-3 h-3" />
                 Platform Capabilities
               </div>
               <h2 className="text-8xl font-black text-em-950 uppercase tracking-tighter leading-none text-center">
                 Universal <br /> <span className="text-em-950/5 outline-text">Control Center</span>
               </h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-12 md:grid-rows-2 gap-8 h-auto md:h-[900px]">
               {/* Feature Large */}
               <div className="bento-card md:col-span-8 md:row-span-2 glass-panel-deep p-20 flex flex-col relative overflow-hidden border-2 border-em-950/5 group" onMouseEnter={handleMouseEnter} onMouseLeave={handleMouseLeave}>
                  <CornerBrackets position="top-0 left-0" />
                  <CornerBrackets position="top-0 right-0" />
                  <div className="absolute top-0 right-0 p-20 opacity-[0.03] group-hover:opacity-[0.06] transition-opacity">
                    <BrainCircuit className="w-80 h-80" />
                  </div>
                  <div className="w-20 h-20 bg-em-950 flex items-center justify-center mb-16 shadow-2xl relative">
                     <BrainCircuit className="w-10 h-10 text-white" />
                     <div className="absolute -bottom-3 -right-3 w-8 h-8 bg-em-600/10 backdrop-blur-md border border-em-950/5" />
                  </div>
                  <h3 className="text-7xl font-black text-em-950 mb-10 uppercase tracking-tighter leading-none">AI Macro <br/> Orchestration</h3>
                  <p className="text-lg font-bold text-em-950/40 uppercase tracking-[0.2em] max-w-lg leading-relaxed mb-16">
                    Autonomous calculation engine that translates clinical requirements into adaptive exchange lists with milligram precision across all Filipino food variants.
                  </p>
                  
                  <div className="mt-auto grid grid-cols-3 gap-12 pt-16 border-t border-em-950/5">
                     {[
                       { label: "Neural Path", val: "94%" },
                       { label: "Quantization", val: "0.01g" },
                       { label: "Sync Speed", val: "14ms" }
                     ].map((item, i) => (
                       <div key={i} className="flex flex-col gap-4">
                          <span className="text-[10px] font-black uppercase tracking-[0.4em] text-em-950/30">{item.label}</span>
                          <span className="text-3xl font-black text-em-950 tabular-nums">{item.val}</span>
                       </div>
                     ))}
                  </div>
               </div>

               {/* Feature Medium */}
               <div className="bento-card md:col-span-4 md:row-span-1 glass-emerald p-12 flex flex-col justify-center relative overflow-hidden group">
                  <div className="absolute -bottom-10 -right-10 opacity-[0.05] group-hover:scale-110 transition-transform">
                     <Globe className="w-64 h-64 text-white" />
                  </div>
                  <ScanLine className="w-16 h-16 text-em-500 mb-10" />
                  <h3 className="text-3xl font-black text-white mb-6 uppercase tracking-tighter">Biometric Telemetry</h3>
                  <p className="text-sm font-bold text-white/40 uppercase tracking-[0.3em] leading-relaxed">
                    Real-time metabolic tracking and anthropometric drift quantification.
                  </p>
               </div>

               {/* Feature Small */}
               <div className="bento-card md:col-span-2 md:row-span-1 glass-panel p-10 flex flex-col items-center justify-center text-center gap-8 border-b-8 border-b-em-950">
                  <Database className="w-10 h-10 text-em-950" />
                  <span className="text-[11px] font-black uppercase tracking-[0.3em] text-em-950">Registry Core</span>
               </div>

               {/* Feature Small */}
               <div className="bento-card md:col-span-2 md:row-span-1 glass-panel p-10 flex flex-col items-center justify-center text-center gap-8 border-b-8 border-b-em-600">
                  <ShieldCheck className="w-10 h-10 text-em-600" />
                  <span className="text-[11px] font-black uppercase tracking-[0.3em] text-em-950">Data Security</span>
               </div>

            </div>
          </div>
        </section>

        {/* CTA - INDUSTRIAL CALL */}
        <section className="py-60 px-12 flex justify-center relative overflow-hidden">
          <div className="absolute inset-0 bg-em-950 opacity-[0.02] z-[-1]" />
          <TechnicalMarker label="E-NODE: 04" position="bottom-32 left-32" />
          
          <div className="max-w-[80rem] w-full glass-panel-deep p-32 text-center relative overflow-hidden border-2 border-em-950" onMouseEnter={handleMouseEnter} onMouseLeave={handleMouseLeave}>
             <CornerBrackets position="top-0 left-0" />
             <CornerBrackets position="bottom-0 right-0" />
             <div className="absolute top-0 right-0 p-16 opacity-[0.02]">
                <Terminal className="w-96 h-96" />
             </div>
             
             <div className="section-chip mx-auto mb-12">Operational Ready State</div>
             <h2 className="text-8xl md:text-[12rem] font-black text-em-950 mb-16 uppercase leading-none tracking-tighter">
               Initialize <br/> <span className="text-em-950/5 outline-text">Standard</span>
             </h2>
             <p className="text-xl font-bold text-em-950/40 uppercase tracking-[0.3em] mb-24 max-w-2xl mx-auto leading-relaxed">
               Deploy the clinical vanguard. High-fidelity dietary telemetry orchestrated through a single unified matrix.
             </p>
             
             <div className="flex flex-col md:flex-row gap-10 justify-center">
                <button className="bg-em-950 text-white px-16 py-8 text-[11px] font-black uppercase tracking-[0.4em] shadow-2xl hover:bg-em-800 transition-all flex items-center gap-6 relative group">
                  <div className="absolute inset-0 border border-white/20 scale-x-0 group-hover:scale-x-100 transition-transform origin-left" />
                  Begin Integration <ChevronRight className="w-4 h-4" />
                </button>
                <button className="bg-white text-em-950 border-2 border-em-950 px-16 py-8 text-[11px] font-black uppercase tracking-[0.4em] hover:bg-em-50 transition-all">
                  Documentation
                </button>
             </div>
          </div>
        </section>

      </main>

      {/* FOOTER - TECHNICAL LAYOUT */}
      <footer className="bg-offwhite py-40 px-16 border-t border-em-950/10 relative z-10">
        <div className="max-w-[100rem] mx-auto grid grid-cols-1 md:grid-cols-12 gap-32">
          <div className="md:col-span-6">
            <div className="flex items-center gap-4 mb-12">
              <div className="w-8 h-8 bg-em-950" />
              <span className="font-black text-3xl tracking-tighter uppercase">Nutri<span className="text-em-600">Deq</span></span>
            </div>
            <p className="text-sm font-bold text-em-950/40 uppercase tracking-[0.25em] leading-relaxed max-w-md">
              Surgical-grade clinical nutrition engine. Quantifying dietary telemetry through adaptive socio-cultural logic and milligram-precision orchestration.
            </p>
          </div>
          
          <div className="md:col-span-2 flex flex-col gap-10">
            <span className="text-[11px] font-black uppercase tracking-[0.5em] text-em-950">Modules</span>
            <div className="flex flex-col gap-6 text-[12px] font-bold text-em-950/40 uppercase tracking-[0.2em]">
              <a href="#" className="hover:text-em-950 transition-colors">Macro_Logic</a>
              <a href="#" className="hover:text-em-950 transition-colors">Telemetry_Core</a>
              <a href="#" className="hover:text-em-950 transition-colors">Registry_DB</a>
              <a href="#" className="hover:text-em-950 transition-colors">Equity_Matrix</a>
            </div>
          </div>

          <div className="md:col-span-2 flex flex-col gap-10">
            <span className="text-[11px] font-black uppercase tracking-[0.5em] text-em-950">Network</span>
            <div className="flex flex-col gap-6 text-[12px] font-bold text-em-950/40 uppercase tracking-[0.2em]">
               <a href="#" className="hover:text-em-950 transition-colors">Node_Status</a>
               <a href="#" className="hover:text-em-950 transition-colors">Research_Lab</a>
               <a href="#" className="hover:text-em-950 transition-colors">Sec_Protocol</a>
               <a href="#" className="hover:text-em-950 transition-colors">Contact_Auth</a>
            </div>
          </div>

          <div className="md:col-span-2 flex flex-col items-end justify-end">
             <div className="w-full p-8 border border-em-950/10 relative">
                <span className="text-[9px] font-black uppercase tracking-[0.4em] text-em-950/20 mb-3 block">System Instance</span>
                <div className="font-mono text-[11px] text-em-600 flex items-center gap-3">
                   <div className="w-2 h-2 bg-em-600 animate-pulse" />
                   <span>NODE_ONLINE :: v4.0.2</span>
                </div>
             </div>
          </div>
        </div>

        <div className="max-w-[100rem] mx-auto flex flex-col md:flex-row justify-between items-center gap-12 pt-24 mt-32 border-t border-em-950/5 text-[10px] font-black uppercase tracking-[0.5em] text-em-950/15">
           <div className="flex gap-16">
             <a href="#" className="hover:text-em-950 transition-colors">Privacy_Architecture</a>
             <a href="#" className="hover:text-em-950 transition-colors">Service_Level</a>
             <a href="#" className="hover:text-em-950 transition-colors">Governance</a>
           </div>
           <span>&copy; 2026 NUTRIDEQ_ENGINE_CORE :: QUANTIFIED_INTELLIGENCE</span>
        </div>
      </footer>

      {/* Global CSS for Technical Effects */}
      <style jsx global>{`
        .outline-text {
          -webkit-text-stroke: 1.5px rgba(2, 26, 20, 0.12);
        }
        @media (max-width: 768px) {
          .outline-text {
            -webkit-text-stroke: 0.5px rgba(2, 26, 20, 0.12);
          }
        }
      `}</style>

    </div>
  );
}
