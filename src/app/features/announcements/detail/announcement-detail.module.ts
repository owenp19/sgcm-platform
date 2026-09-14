import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { AnnouncementDetailPageRoutingModule } from './announcement-detail-routing.module';
import { AnnouncementDetailPage } from './announcement-detail.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [CommonModule, IonicModule, AnnouncementDetailPageRoutingModule, SharedModule],
  declarations: [AnnouncementDetailPage],
})
export class AnnouncementDetailPageModule {}
