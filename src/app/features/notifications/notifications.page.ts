import { Component, OnInit } from '@angular/core';
import { NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  arrowBackOutline,
  checkmarkCircleOutline,
  documentTextOutline,
  alertCircleOutline,
  informationCircleOutline,
  timeOutline,
  closeOutline,
  trashOutline,
  notificationsOutline,
  checkmarkDoneOutline,
} from 'ionicons/icons';
import { NotificationsService, NotificationItem } from '../../services/notifications.service';

type NotificationFilter = 'all' | 'unread';

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.page.html',
  styleUrls: ['./notifications.page.scss'],
  standalone: false,
})
export class NotificationsPage implements OnInit {
  public notifications: NotificationItem[] = [];

  public filter: NotificationFilter = 'all';

  constructor(
    private readonly navCtrl: NavController,
    private readonly notificationsService: NotificationsService,
  ) {
    addIcons({
      arrowBackOutline,
      checkmarkCircleOutline,
      documentTextOutline,
      alertCircleOutline,
      informationCircleOutline,
      timeOutline,
      closeOutline,
      trashOutline,
      notificationsOutline,
      checkmarkDoneOutline,
    });
  }

  public get filteredNotifications(): NotificationItem[] {
    if (this.filter === 'unread') {
      return this.notifications.filter((n) => !n.read);
    }
    return this.notifications;
  }

  public setFilter(filter: NotificationFilter): void {
    this.filter = filter;
  }

  public ngOnInit(): void {
    this.loadNotifications();
  }

  public get unreadCount(): number {
    return this.notifications.filter((n) => !n.read).length;
  }

  public goBack(): void {
    void this.navCtrl.back();
  }

  public markAsRead(item: NotificationItem): void {
    item.read = true;
    this.notificationsService.markAsRead(item.id).subscribe();
  }

  public clearAll(): void {
    this.notificationsService.clearAll().subscribe(() => {
      this.notifications = [];
    });
  }

  private loadNotifications(): void {
    this.notificationsService.getAll().subscribe({
      next: (res) => {
        this.notifications = res.notifications.map((n) => ({
          ...n,
          icon: this.getIconForType(n.type),
          date: this.formatDate(n.date),
        }));
      },
      error: () => {
        this.notifications = [];
      },
    });
  }

  private getIconForType(type: string): string {
    switch (type) {
      case 'success': return 'checkmark-circle-outline';
      case 'alert': return 'alert-circle-outline';
      default: return 'information-circle-outline';
    }
  }

  private formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }
}
