import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { Reservation, ReservationStatus } from './schemas/reservation.schema';
import {
  CreateReservationDto,
  UpdateReservationDto,
} from './dto/reservation.dto';
import { ListingsService } from '../listings/listings.service';

@Injectable()
export class ReservationsService {
  constructor(
    @InjectModel(Reservation.name) private reservationModel: Model<Reservation>,
    private readonly listingsService: ListingsService,
  ) {}

  async create(
    createReservationDto: CreateReservationDto,
    userId: string,
  ): Promise<Reservation> {
    const listing = await this.listingsService.findOne(
      createReservationDto.listing,
    );
    if (!listing) {
      throw new NotFoundException(
        `Listing ${createReservationDto.listing} not found`,
      );
    }

    const created = new this.reservationModel({
      ...createReservationDto,
      user: userId,
      status: ReservationStatus.PENDING,
    });
    return created.save();
  }

  async findAll(query: any = {}): Promise<Reservation[]> {
    return this.reservationModel
      .find(query)
      .populate('listing')
      .populate('user', '-password')
      .exec();
  }

  async findOne(id: string): Promise<Reservation> {
    return this.reservationModel
      .findById(id)
      .populate('listing')
      .populate('user', '-password')
      .exec();
  }

  async update(
    id: string,
    updateReservationDto: UpdateReservationDto,
  ): Promise<Reservation> {
    return this.reservationModel
      .findByIdAndUpdate(id, updateReservationDto, { new: true })
      .populate('listing')
      .populate('user', '-password')
      .exec();
  }

  async remove(id: string): Promise<Reservation> {
    return this.reservationModel.findByIdAndDelete(id).exec();
  }

  async findByUser(userId: string): Promise<Reservation[]> {
    return this.reservationModel
      .find({ user: userId })
      .populate('listing')
      .populate('user', '-password')
      .exec();
  }

  async findByListing(listingId: string): Promise<Reservation[]> {
    return this.reservationModel
      .find({ listing: listingId })
      .populate('listing')
      .populate('user', '-password')
      .exec();
  }

  async updateStatus(
    id: string,
    status: ReservationStatus,
  ): Promise<Reservation> {
    return this.update(id, { status });
  }
}
