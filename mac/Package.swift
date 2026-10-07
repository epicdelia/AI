// swift-tools-version:5.9
import PackageDescription

// FlowCore: pure logic (prompts, transcript turns, AI polish client, text shaping), unit-tested.
// Flow: the menu-bar app (hotkey, microphone, AssemblyAI socket, typing into the focused app).
let package = Package(
    name: "Flow",
    platforms: [.macOS(.v13)],
    targets: [
        .target(name: "FlowCore"),
        .executableTarget(name: "Flow", dependencies: ["FlowCore"]),
        .testTarget(name: "FlowCoreTests", dependencies: ["FlowCore"]),
    ]
)
