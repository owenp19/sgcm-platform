import { Component, Input } from '@angular/core';
import { NavController } from '@ionic/angular';
import { AuthService } from '../../../services/auth.service';
import { addIcons } from 'ionicons';
import {
  homeOutline,
  barChartOutline,
  peopleOutline,
  documentTextOutline,
  clipboardOutline,
  calendarOutline,
  shieldOutline,
  personOutline,
  logOutOutline,
  menuOutline,
  arrowBackOutline,
  searchOutline,
  chevronBackOutline,
  chevronForwardOutline,
  swapHorizontalOutline,
  personAddOutline,
  briefcaseOutline,
  eyeOutline,
  checkmarkOutline,
  closeOutline,
  documentOutline,
  printOutline,
  alertCircleOutline,
  timeOutline,
  mapOutline,
  locateOutline,
} from 'ionicons/icons';

@Component({
  selector: 'app-admin-layout',
  templateUrl: './admin-layout.component.html',
  styleUrls: ['./admin-layout.component.scss'],
  standalone: false,
})
export class AdminLayoutComponent {
  @Input() title = '';
  @Input() totalCount = '';
  @Input() backRoute = '/admin/dashboard';

  sidebarOpen = false;
  currentUser = this.authService.getUser();

  constructor(
    public readonly authService: AuthService,
    private readonly navCtrl: NavController,
  ) {
    addIcons({
      homeOutline,
      barChartOutline,
      peopleOutline,
      documentTextOutline,
      clipboardOutline,
      calendarOutline,
      shieldOutline,
      personOutline,
      logOutOutline,
      menuOutline,
      arrowBackOutline,
      searchOutline,
      chevronBackOutline,
      chevronForwardOutline,
      swapHorizontalOutline,
      personAddOutline,
      briefcaseOutline,
      eyeOutline,
      checkmarkOutline,
      closeOutline,
documentOutline,
      printOutline,
      alertCircleOutline,
      timeOutline,
      mapOutline,
      locateOutline,
    });
  }

  getUserInitials(): string {
    const user = this.currentUser;
    if (!user) return 'FN';
    const first = user.firstName?.trim().charAt(0) || '';
    const last = user.lastName?.trim().charAt(0) || '';
    return `${first}${last}`.toUpperCase();
  }

  get isAdmin(): boolean {
    return this.authService.hasAnyRole('admin');
  }

  goBack() {
    this.navCtrl.navigateBack(this.backRoute);
  }

  async logout() {
    await this.authService.logout();
  }
}
