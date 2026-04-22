import SwiftUI

struct MainTabView: View {
    var body: some View {
        TabView {
            SubmitScoreView()
                .tabItem {
                    Label("打分上报", systemImage: "square.and.arrow.up")
                }

            ProfileView()
                .tabItem {
                    Label("用户中心", systemImage: "person.2")
                }
        }
    }
}

