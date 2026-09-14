import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { AnnouncementListPageRoutingModule } from './announcement-list-routing.module';
import { AnnouncementListPage } from './announcement-list.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [CommonModule, IonicModule, AnnouncementListPageRoutingModule, SharedModule],
  declarations: [AnnouncementListPage],
})
export class AnnouncementListPageModule {}
