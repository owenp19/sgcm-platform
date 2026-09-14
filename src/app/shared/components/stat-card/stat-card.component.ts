import { Component, Input, Output, EventEmitter } from '@angular/core';
import { addIcons } from 'ionicons';
import {
  ellipseOutline,
  trendingUpOutline,
  trendingDownOutline,
  removeOutline,
} from 'ionicons/icons';

@Component({
  selector: 'app-stat-card',
  templateUrl: './stat-card.component.html',
  styleUrls: ['./stat-card.component.scss'],
  standalone: false,
})
export class StatCardComponent {
  @Input() label = '';
  @Input() value: number | string = 0;
  @Input() subtitle = '';
  @Input() icon = 'ellipse-outline';
  @Input() color: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'dark' = 'primary';
  @Input() trend: 'up' | 'down' | 'neutral' | null = null;
  @Input() trendValue = '';
  @Input() link: string | null = null;
  @Input() loading = false;

  @Output() cardClick = new EventEmitter<void>();

  constructor() {
    addIcons({ ellipseOutline, trendingUpOutline, trendingDownOutline, removeOutline });
  }

  get colorClass(): string {
    return `stat-${this.color}`;
  }
}
